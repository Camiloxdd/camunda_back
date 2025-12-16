<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Mime\Email;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Repository\RequisicionesRepository;
use App\Service\RequisitionService;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use ConvertApi\ConvertApi;

use App\Service\RequisitionPdfService;
use Psr\Log\LoggerInterface;
use Throwable;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

#[Route('/api', name: 'api_')]
class RequisitionController extends AbstractController
{
    private Connection $conn;
    private RequisitionService $service;

    public function __construct(Connection $connection, RequisitionService $service, private LoggerInterface $logger)
    {
        $this->conn = $connection;
        $this->service = $service;
    }

    // Helper CORS: usar en endpoints que reciben llamadas desde el front (preflight + respuestas)
    private function getCorsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-User-Id, X-User-Name, X-User-Area',
            'Access-Control-Allow-Credentials' => 'true',
        ];
    }

    #[Route('/requisicion/create', name: 'requisition_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $solicitante = $data['solicitante'] ?? null;
            $productos = $data['productos'] ?? [];
            $processInstanceKey = $data['processInstanceKey'] ?? null;

            if (!$solicitante || empty($productos)) {
                return $this->json(['message' => 'Datos incompletos en la solicitud'], 400);
            }

            $nombre = $solicitante['nombre'] ?? null;
            $fecha = $solicitante['fecha'] ?? null;
            $fechaRequeridoEntrega = $solicitante['fechaRequeridoEntrega'] ?? null;
            $tiempoAproximadoGestion = $solicitante['tiempoAproximadoGestion'] ?? null;
            $justificacion = $solicitante['justificacion'] ?? null;
            $area = $solicitante['area'] ?? null;
            $sede = $solicitante['sede'] ?? null;
            $urgencia = $solicitante['urgencia'] ?? null;
            $presupuestada = !empty($solicitante['presupuestada']);

            // calcular valor total
            $valorTotal = 0;
            foreach ($productos as $p) {
                $valorTotal += floatval($p['valorEstimado'] ?? 0);
            }

            $this->conn->beginTransaction();

            // insertar requisicion
            $this->conn->executeStatement(
                'INSERT INTO requisiciones (nombre_solicitante, fecha, fecha_requerido_entrega, tiempoAproximadoGestion, justificacion, area, sede, urgencia, presupuestada, valor_total, status, process_instance_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$nombre, $fecha, $fechaRequeridoEntrega, $tiempoAproximadoGestion, $justificacion, $area, $sede, $urgencia, $presupuestada ? 1 : 0, $valorTotal, 'pendiente', $processInstanceKey]
            );

            $requisicionId = (int)$this->conn->lastInsertId();

            // insertar productos (batch)
            $values = [];
            foreach ($productos as $p) {
                $values[] = [
                    $requisicionId,
                    $p['nombre'] ?? '',
                    $p['cantidad'] ?? 1,
                    $p['descripcion'] ?? '',
                    !empty($p['compraTecnologica']) ? 1 : 0,
                    !empty($p['ergonomico']) ? 1 : 0,
                    $p['valorEstimado'] ?? 0,
                    $p['centroCosto'] ?? '',
                    $p['cuentaContable'] ?? '',
                    null
                ];
            }
            // Utilizamos un INSERT multiple manual usando prepared statements
            foreach ($values as $row) {
                $this->conn->executeStatement(
                    'INSERT INTO requisicion_productos (requisicion_id, nombre, cantidad, descripcion, compra_tecnologica, ergonomico, valor_estimado, centro_costo, cuenta_contable, aprobado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $row
                );
            }

            // determinar roles necesarios (misma lógica)
            $SMLV = 1300000;
            $limite = $SMLV * 10;
            $requiereAltas = $valorTotal >= $limite;

            $tieneErgonomico = false;
            $tieneTecnologico = false;
            foreach ($productos as $p) {
                if (!empty($p['ergonomico'])) $tieneErgonomico = true;
                if (!empty($p['compraTecnologica'])) $tieneTecnologico = true;
            }

            $rolesNecesarios = [];
            if ($tieneTecnologico) array_push($rolesNecesarios, 'dicTYP', 'gerTyC');
            if ($tieneErgonomico) array_push($rolesNecesarios, 'dicSST', 'gerSST');
            if ($requiereAltas && !$presupuestada) array_push($rolesNecesarios, 'gerAdmin', 'gerGeneral');

            $aprobadores = [];
            if (!empty($rolesNecesarios)) {
                // usar IN (...) con parámetros
                $placeholders = implode(',', array_fill(0, count($rolesNecesarios), '?'));
                $sql = "SELECT nombre, cargo AS rol, area FROM user WHERE cargo IN ($placeholders)";
                $rows = $this->conn->fetchAllAssociative($sql, $rolesNecesarios);

                foreach ($rolesNecesarios as $rol) {
                    foreach ($rows as $u) {
                        if ($u['rol'] === $rol) {
                            $aprobadores[] = $u;
                            break;
                        }
                    }
                }
            }

            // insertar aprobaciones con orden y visibilidad
            $orden = 1;
            foreach ($aprobadores as $i => $aprob) {
                $visible = ($i === 0) ? 1 : 0;
                $this->conn->executeStatement(
                    'INSERT INTO requisicion_aprobaciones (requisicion_id, rol_aprobador, nombre_aprobador, area, estado, orden, visible) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$requisicionId, $aprob['rol'], $aprob['nombre'], $aprob['area'], 'pendiente', $orden, $visible]
                );
                $orden++;
            }

            $this->conn->commit();

            return $this->json([
                'message' => 'Requisición creada correctamente con aprobadores asignados',
                'requisicionId' => $requisicionId,
                'valorTotal' => $valorTotal,
                'aprobadores' => $aprobadores
            ], 201);
        } catch (Throwable $e) {
            if ($this->conn->isTransactionActive()) {
                $this->conn->rollBack();
            }
            return $this->json(['message' => 'Error al crear la requisición', 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/pendientes', name: 'requisition_pendientes', methods: ['GET', 'OPTIONS'])]
    public function pendientes(Request $request): JsonResponse
    {
        $corsHeaders = $this->getCorsHeaders();
        // Responder preflight OPTIONS
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->json(null, 200, $corsHeaders);
        }

        try {
            // Identificación del usuario: cabecera X-User-Id (ajustar según tu auth)
            $userId = $request->headers->get('X-User-Id') ?? $request->query->get('userId');
            if (!$userId) {
                return $this->json(['message' => 'User id required (X-User-Id header)'], 400, $corsHeaders);
            }

            $userRow = $this->conn->fetchAssociative('SELECT solicitante, nombre, cargo, area FROM user WHERE id = ?', [(int)$userId]);

            if ($userRow && ($userRow['solicitante'] == 1 || $userRow['solicitante'] === '1')) {
                $requisiciones = $this->conn->fetchAllAssociative(
                    'SELECT id AS requisicion_id, nombre_solicitante, fecha, justificacion, area, sede, urgencia, presupuestada, valor_total, status FROM requisiciones WHERE nombre_solicitante = ? ORDER BY fecha DESC',
                    [$userRow['nombre']]
                );
                if (empty($requisiciones)) {
                    return $this->json([], 200, $corsHeaders);
                }
                $ids = array_column($requisiciones, 'requisicion_id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $productos = $this->conn->fetchAllAssociative(
                    "SELECT id, requisicion_id, nombre, descripcion, cantidad, valor_estimado, compra_tecnologica, ergonomico, aprobado FROM requisicion_productos WHERE requisicion_id IN ($placeholders) AND (aprobado IS NULL OR aprobado != 'rechazado')",
                    $ids
                );
                $result = [];
                foreach ($requisiciones as $r) {
                    $r['productos'] = array_values(array_filter($productos, fn($p) => $p['requisicion_id'] == $r['requisicion_id']));
                    $result[] = $r;
                }
                return $this->json($result, 200, $corsHeaders);
            }

            // roles que pueden aprobar
            $cargo = $userRow['cargo'] ?? null;
            $area = $userRow['area'] ?? null;
            $rolesAprobadores = ['dicTYP', 'gerTyC', 'dicSST', 'gerSST', 'gerAdmin', 'gerGeneral'];
            if (!in_array($cargo, $rolesAprobadores, true)) {
                return $this->json(['message' => 'No autorizado para aprobar requisiciones'], 403, $corsHeaders);
            }

            // construir query similar al original
            $sql = "SELECT r.id AS requisicion_id, r.nombre_solicitante, r.fecha, r.justificacion, r.area, r.sede, r.urgencia, r.presupuestada, r.valor_total, r.status,
                           a.id AS aprobacion_id, a.area AS area_aprobacion, a.rol_aprobador, a.nombre_aprobador, a.estado AS estado_aprobacion, a.orden, a.visible
                    FROM requisiciones r
                    INNER JOIN requisicion_aprobaciones a ON r.id = a.requisicion_id
                    WHERE a.estado = 'pendiente'";

            $params = [];
            if ($cargo !== 'gerGeneral') {
                $sql .= " AND a.rol_aprobador = ? AND a.area = ?";
                $params[] = $cargo;
                $params[] = $area;
            }
            $sql .= " ORDER BY r.fecha DESC";

            $rows = $this->conn->fetchAllAssociative($sql, $params);

            // deduplicar por requisicion_id
            $map = [];
            foreach ($rows as $r) {
                if (!isset($map[$r['requisicion_id']])) $map[$r['requisicion_id']] = $r;
            }
            $unique = array_values($map);
            if (empty($unique)) return $this->json([], 200, $corsHeaders);

            $ids = array_map(fn($r) => $r['requisicion_id'], $unique);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $productos = $this->conn->fetchAllAssociative(
                "SELECT id, requisicion_id, nombre, descripcion, cantidad, valor_estimado, compra_tecnologica, ergonomico, aprobado FROM requisicion_productos WHERE requisicion_id IN ($placeholders) AND (aprobado IS NULL OR aprobado != 'rechazado')",
                $ids
            );

            $result = [];
            foreach ($unique as $r) {
                $r['productos'] = array_values(array_filter($productos, fn($p) => $p['requisicion_id'] == $r['requisicion_id']));
                $result[] = $r;
            }

            return $this->json($result, 200, $corsHeaders);
        } catch (Throwable $e) {
            return $this->json(['message' => 'Error al obtener requisiciones pendientes', 'error' => $e->getMessage()], 500, $corsHeaders);
        }
    }

    #[Route('/requisiciones/{id}', name: 'requisition_show', methods: ['GET'])]
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $req = $this->conn->fetchAssociative('SELECT id, nombre_solicitante, fecha, justificacion, area, sede, urgencia, presupuestada, valor_total, status FROM requisiciones WHERE id = ?', [$id]);
            if (!$req) return $this->json(['message' => 'Requisición no encontrada'], 404);

            // 🔥 OBTENER TODOS LOS PRODUCTOS (para referencia/histórico)
            $todosLosProductos = $this->conn->fetchAllAssociative('SELECT id, nombre, descripcion, cantidad, valor_estimado, compra_tecnologica, ergonomico, aprobado, centro_costo, cuenta_contable FROM requisicion_productos WHERE requisicion_id = ?', [$id]);

            // 🔥 FILTRAR SOLO PRODUCTOS NO RECHAZADOS (los que se muestran en la UI)
            $productosVisibles = array_filter($todosLosProductos, function ($p) {
                return $p['aprobado'] !== 'rechazado';
            });

            // currentUser: si Symfony maneja auth, reemplazar por getUser()
            $userId = $request->headers->get('X-User-Id') ?? null;
            $currentUser = $userId ? $this->conn->fetchAssociative('SELECT id, nombre, cargo, area FROM user WHERE id = ?', [(int)$userId]) : null;

            // obtener progreso de aprobación: simple query agregada
            $approvers = $this->conn->fetchAllAssociative('SELECT id, rol_aprobador, nombre_aprobador, area, estado, orden, visible, fecha_aprobacion FROM requisicion_aprobaciones WHERE requisicion_id = ? ORDER BY orden ASC', [$id]);

            return $this->json([
                'requisicion' => $req,
                'productos' => array_values($productosVisibles), // 🔥 SIN rechazados
                'currentUser' => $currentUser,
                'approvalProgress' => [
                    'approvers' => $approvers
                ]
            ]);
        } catch (Throwable $e) {
            return $this->json(['message' => 'Error al obtener detalles', 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/aprobacion', name: 'requisition_aprobacion', methods: ['GET'])]
    public function aprobacion(int $id): JsonResponse
    {
        try {
            $approvers = $this->conn->fetchAllAssociative('SELECT id, rol_aprobador, nombre_aprobador, area, estado, orden, visible, fecha_aprobacion FROM requisicion_aprobaciones WHERE requisicion_id = ? ORDER BY orden ASC', [$id]);

            $counts = $this->conn->fetchAssociative('SELECT COUNT(*) AS total, SUM(CASE WHEN estado = \'aprobada\' THEN 1 ELSE 0 END) AS aprobadas, SUM(CASE WHEN estado = \'pendiente\' THEN 1 ELSE 0 END) AS pendientes, MAX(CASE WHEN estado = \'aprobada\' THEN orden ELSE NULL END) AS lastApprovedOrder FROM requisicion_aprobaciones WHERE requisicion_id = ?', [$id]);

            return $this->json([
                'approvers' => $approvers,
                'counts' => $counts
            ]);
        } catch (Throwable $e) {
            return $this->json(['message' => 'Error al obtener progreso de aprobación', 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/verificar-aprobacion', name: 'requisition_verificar_aprobacion', methods: ['GET'])]
    public function verificarAprobacion(int $id, Request $request): JsonResponse
    {
        try {
            $userName = $request->headers->get('X-User-Name');
            $userArea = $request->headers->get('X-User-Area');

            if (!$userName || !$userArea) {
                return $this->json(['message' => 'Usuario no identificado'], 400);
            }

            // 🔥 Obtener el aprobador DEL USUARIO ACTUAL
            $aprobador = $this->conn->fetchAssociative(
                "SELECT id, rol_aprobador, estado, visible, orden FROM requisicion_aprobaciones 
                 WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?",
                [$id, $userName, $userArea]
            );

            if (!$aprobador) {
                // El usuario no es aprobador de esta requisición
                return $this->json([
                    'yaAprobaste' => false,
                    'puedeAprobar' => false,
                    'mensaje' => 'No eres aprobador de esta requisición'
                ]);
            }

            $estadoAprobador = strtolower($aprobador['estado'] ?? '');
            $esVisible = $aprobador['visible'] == 1 || $aprobador['visible'] === true;
            $estaPendiente = $estadoAprobador === 'pendiente';

            // 🔥 LÓGICA CLARA:
            // - yaAprobaste = true si el estado es "aprobada"
            // - puedeAprobar = true si: está pendiente, es visible Y no ha aprobado

            $yaAprobaste = $estadoAprobador === 'aprobada';
            $puedeAprobar = $estaPendiente && $esVisible && !$yaAprobaste;

            return $this->json([
                'yaAprobaste' => $yaAprobaste,
                'puedeAprobar' => $puedeAprobar,
                'estado' => $estadoAprobador,
                'visible' => $esVisible,
                'pendiente' => $estaPendiente
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Error al verificar aprobación', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/aprobar-items', name: 'requisition_aprobar_items', methods: ['PUT', 'OPTIONS'])]
    public function aprobarItems(int $id, Request $request): JsonResponse
    {
        $corsHeaders = $this->getCorsHeaders();

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->json(null, 200, $corsHeaders);
        }

        try {
            $data = $request->toArray();
            $userName = $request->headers->get('X-User-Name');
            $userArea = $request->headers->get('X-User-Area');

            $this->conn->beginTransaction();

            // 1️⃣ OBTENER DATOS DEL APROBADOR ACTUAL
            $aprobadorActual = $this->conn->fetchAssociative(
                "SELECT id, rol_aprobador, orden FROM requisicion_aprobaciones WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?",
                [$id, $userName, $userArea]
            );
            if (!$aprobadorActual) {
                $this->conn->rollBack();
                return $this->json(['message' => 'No se encontró aprobación correspondiente al usuario actual.'], 404, $corsHeaders);
            }
            $ordenActual = $aprobadorActual['orden'];

            // 2️⃣ Verificar si quedan productos relevantes pendientes para este aprobador
            // (productos relevantes = productos de su área/rol que aún no han sido aprobados/rechazados)
            $rolAprobador = $aprobadorActual['rol_aprobador'];
            $technoRoles = ['dicTYP', 'gerTyC'];
            $sstRoles = ['dicSST', 'gerSST'];
            if (in_array($rolAprobador, $technoRoles)) {
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id, aprobado FROM requisicion_productos WHERE requisicion_id = ? AND compra_tecnologica = 1",
                    [$id]
                );
            } elseif (in_array($rolAprobador, $sstRoles)) {
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id, aprobado FROM requisicion_productos WHERE requisicion_id = ? AND ergonomico = 1",
                    [$id]
                );
            } else {
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id, aprobado FROM requisicion_productos WHERE requisicion_id = ?",
                    [$id]
                );
            }
            $pendientes = array_filter($productosRelevantes, fn($p) => !$p['aprobado'] || $p['aprobado'] === '');

            // 3️⃣ Si quedan productos pendientes, no permitir aprobar la requisición
            if (count($pendientes) > 0) {
                $this->conn->rollBack();
                return $this->json(['message' => 'Aún hay productos pendientes de decisión.'], 400, $corsHeaders);
            }

            // 4️⃣ Marcar aprobación del usuario actual
            $rechazadosDelRol = array_filter($productosRelevantes, fn($p) => $p['aprobado'] === 'rechazado');
            if (count($rechazadosDelRol) === count($productosRelevantes)) {
                $this->conn->executeStatement(
                    "UPDATE requisicion_aprobaciones SET estado = 'rechazada', fecha_aprobacion = NOW(), visible = 0 WHERE id = ?",
                    [$aprobadorActual['id']]
                );
            } else {
                $this->conn->executeStatement(
                    "UPDATE requisicion_aprobaciones SET estado = 'aprobada', fecha_aprobacion = NOW(), visible = 0 WHERE id = ?",
                    [$aprobadorActual['id']]
                );
            }
            // Activar siguiente aprobador si existe
            $this->conn->executeStatement(
                "UPDATE requisicion_aprobaciones SET visible = 1 WHERE requisicion_id = ? AND orden = ? AND estado = 'pendiente'",
                [$id, $ordenActual + 1]
            );

            // 5️⃣ Calcular nuevo valor total solo con productos aprobados
            $sum = $this->conn->fetchAssociative(
                "SELECT SUM(COALESCE(valor_estimado, 0) * COALESCE(cantidad, 1)) AS nuevo_total FROM requisicion_productos WHERE requisicion_id = ? AND (aprobado = 'aprobado' OR aprobado = 1)",
                [$id]
            );
            $nuevoTotal = $sum['nuevo_total'] ?? 0;
            $this->conn->executeStatement(
                "UPDATE requisiciones SET valor_total = ? WHERE id = ?",
                [$nuevoTotal, $id]
            );

            // 6️⃣ Verificar estado final de la requisición
            $totalProductos = $this->conn->fetchOne(
                "SELECT COUNT(*) FROM requisicion_productos WHERE requisicion_id = ?",
                [$id]
            );
            $approvedCount = $this->conn->fetchOne(
                "SELECT COUNT(*) FROM requisicion_productos WHERE requisicion_id = ? AND (aprobado = 'aprobado' OR aprobado = 1)",
                [$id]
            );
            $rejectedCount = $this->conn->fetchOne(
                "SELECT COUNT(*) FROM requisicion_productos WHERE requisicion_id = ? AND aprobado = 'rechazado'",
                [$id]
            );
            $pendingProductsCount = $this->conn->fetchOne(
                "SELECT COUNT(*) FROM requisicion_productos WHERE requisicion_id = ? AND (aprobado IS NULL OR aprobado = '')",
                [$id]
            );

            $finalStatus = 'pendiente';
            if ($totalProductos > 0) {
                if ($approvedCount === 0 && $rejectedCount === $totalProductos) {
                    $finalStatus = 'rechazada';
                    $this->conn->executeStatement(
                        "UPDATE requisicion_aprobaciones SET estado = 'rechazada', visible = 0 WHERE requisicion_id = ?",
                        [$id]
                    );
                } elseif ($approvedCount === $totalProductos) {
                    $finalStatus = 'aprobada';
                } elseif ($approvedCount > 0 && $pendingProductsCount === 0) {
                    $finalStatus = 'parcialmente-aprobada';
                }
            }
            $this->conn->executeStatement(
                "UPDATE requisiciones SET status = ? WHERE id = ?",
                [$finalStatus, $id]
            );

            // 7️⃣ Verificar si quedan aprobaciones pendientes
            $pendingApproversCount = $this->conn->fetchOne(
                "SELECT COUNT(*) FROM requisicion_aprobaciones WHERE requisicion_id = ? AND estado = 'pendiente'",
                [$id]
            );

            $this->conn->commit();

            return $this->json([
                'message' => 'Aprobación registrada correctamente.',
                'nuevo_total' => $nuevoTotal,
                'status_final' => $finalStatus,
                'pendientes_productos' => $pendingProductsCount,
                'pendientes_aprobadores' => $pendingApproversCount
            ], 200, $corsHeaders);
        } catch (\Throwable $e) {
            if ($this->conn->isTransactionActive()) {
                $this->conn->rollBack();
            }
            return $this->json([
                'message' => 'Error al procesar aprobación',
                'error' => $e->getMessage()
            ], 500, $corsHeaders);
        }
    }

    #[Route('/requisiciones/{id}/productos/estado', name: 'requisiciones_productos_estado', methods: ['PUT', 'OPTIONS'])]
    public function actualizarEstadoProductos(int $id, Request $request): JsonResponse
    {
        $corsHeaders = $this->getCorsHeaders();
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->json(null, 200, $corsHeaders);
        }
        try {
            $data = $request->toArray();
            $decisiones = $data['decisiones'] ?? [];
            $userName = $request->headers->get('X-User-Name');
            $userArea = $request->headers->get('X-User-Area');

            if (!is_array($decisiones)) {
                return $this->json(['message' => 'Formato inválido: decisiones debe ser un array'], 400, $corsHeaders);
            }

            $this->conn->beginTransaction();

            // Obtener rol del aprobador actual
            $aprobadorActual = $this->conn->fetchAssociative(
                "SELECT rol_aprobador FROM requisicion_aprobaciones WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?",
                [$id, $userName, $userArea]
            );
            if (!$aprobadorActual) {
                $this->conn->rollBack();
                return $this->json(['message' => 'No se encontró aprobación correspondiente al usuario actual.'], 404, $corsHeaders);
            }
            $rolAprobador = $aprobadorActual['rol_aprobador'];
            $technoRoles = ['dicTYP', 'gerTyC'];
            $sstRoles = ['dicSST', 'gerSST'];

            // Determinar productos relevantes
            if (in_array($rolAprobador, $technoRoles)) {
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id FROM requisicion_productos WHERE requisicion_id = ? AND compra_tecnologica = 1",
                    [$id]
                );
            } elseif (in_array($rolAprobador, $sstRoles)) {
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id FROM requisicion_productos WHERE requisicion_id = ? AND ergonomico = 1",
                    [$id]
                );
            } else {
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id FROM requisicion_productos WHERE requisicion_id = ?",
                    [$id]
                );
            }
            $idsRelevantes = array_map(fn($p) => $p['id'], $productosRelevantes);

            // Validar que las decisiones sean solo de productos relevantes
            foreach ($decisiones as $d) {
                if (!in_array($d['id'], $idsRelevantes) && !empty($idsRelevantes)) {
                    $this->conn->rollBack();
                    return $this->json(['message' => 'Intentas aprobar/rechazar productos que no son de tu responsabilidad.'], 403, $corsHeaders);
                }
            }

            // Actualizar estado de cada producto decidido
            foreach ($decisiones as $d) {
                $productoId = $d['id'] ?? null;
                $aprobado = isset($d['aprobado']) ? (bool)$d['aprobado'] : false;
                // Siempre registrar la fecha de acción (aprobado o rechazado)
                $fechaAprobado = $d['fecha_aprobado'] ?? (new \DateTime())->format('Y-m-d H:i:s');
                $comentarios = $d['comentarios'] ?? null;
                $usuarioAccion = $d['usuario_accion'] ?? null;

                $this->conn->executeStatement(
                    "UPDATE requisicion_productos SET aprobado = ?, fecha_aprobado = ?, comentarios = ?, usuario_accion = ? WHERE id = ? AND requisicion_id = ?",
                    [
                        $aprobado ? 'aprobado' : 'rechazado',
                        $fechaAprobado,
                        $comentarios,
                        $usuarioAccion,
                        $productoId,
                        $id
                    ]
                );
            }

            $this->conn->commit();
            return $this->json(['message' => 'Estado de productos actualizado correctamente.'], 200, $corsHeaders);
        } catch (\Throwable $e) {
            if ($this->conn->isTransactionActive()) {
                $this->conn->rollBack();
            }
            return $this->json(['message' => 'Error al actualizar productos', 'error' => $e->getMessage()], 500, $corsHeaders);
        }
    }

    #[Route('/aprobador/{nombre}', name: 'requisition_by_approver', methods: ['GET'])]
    public function byApprover(string $nombre): JsonResponse
    {
        try {
            $rows = $this->conn->fetchAllAssociative(
                'SELECT r.id AS requisicion_id, r.valor_total, r.status, r.nombre_solicitante, r.fecha, r.area, r.sede, r.urgencia, r.justificacion, a.estado AS estado_aprobacion
                 FROM requisiciones r
                 INNER JOIN requisicion_aprobaciones a ON r.id = a.requisicion_id
                 WHERE a.nombre_aprobador = ?
                 ORDER BY r.fecha DESC',
                [$nombre]
            );
            return $this->json($rows);
        } catch (Throwable $e) {
            return $this->json(['message' => 'Error al obtener requisiciones por aprobador', 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/meta', name: 'meta', methods: ['GET'])]
    public function meta(): JsonResponse
    {
        try {
            $areasRows = $this->conn->fetchAllAssociative("SELECT DISTINCT area FROM user WHERE area IS NOT NULL AND area != ''");
            $sedesRows = $this->conn->fetchAllAssociative("SELECT DISTINCT sede FROM user WHERE sede IS NOT NULL AND sede != ''");
            $areas = array_map(fn($r) => $r['area'], $areasRows);
            $sedes = array_map(fn($r) => $r['sede'], $sedesRows);
            return $this->json(['areas' => $areas, 'sedes' => $sedes]);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al obtener áreas y sedes', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones', name: 'requisiciones_list', methods: ['GET'])]
    public function listAll(): JsonResponse
    {
        try {

            // Query ligera y altamente performante
            $sql = "
            SELECT 
                id AS requisicion_id,
                nombre_solicitante,
                fecha,
                justificacion,
                area,
                sede,
                urgencia,
                presupuestada,
                valor_total,
                status
            FROM requisiciones
            ORDER BY fecha DESC
        ";

            $rows = $this->conn->fetchAllAssociative($sql);

            return $this->json($rows, 200, $this->getCorsHeaders());
        } catch (\Throwable $e) {

            return $this->json([
                'error' => 'Error al obtener requisiciones',
                'detail' => $e->getMessage()
            ], 500, $this->getCorsHeaders());
        }
    }


    #[Route('/requisiciones/{id}', name: 'requisiciones_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->conn->executeStatement("DELETE FROM requisicion_productos WHERE requisicion_id = ?", [$id]);
            $this->conn->executeStatement("DELETE FROM requisicion_aprobaciones WHERE requisicion_id = ?", [$id]);
            $affected = $this->conn->executeStatement("DELETE FROM requisiciones WHERE id = ?", [$id]);
            if ($affected === 0) return $this->json(['message' => 'Requisición no encontrada'], 404);
            return $this->json(['message' => 'Requisición eliminada correctamente']);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al eliminar requisición', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}', name: 'requisiciones_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $this->conn->executeStatement(
                "UPDATE requisiciones SET nombre_solicitante=?, fecha=?, justificacion=?, area=?, sede=?, urgencia=?, presupuestada=? WHERE id=?",
                [
                    $data['nombre_solicitante'] ?? null,
                    $data['fecha'] ?? null,
                    $data['justificacion'] ?? null,
                    $data['area'] ?? null,
                    $data['sede'] ?? null,
                    $data['urgencia'] ?? null,
                    !empty($data['presupuestada']) ? 1 : 0,
                    $id
                ]
            );
            return $this->json(['message' => 'Requisición actualizada correctamente']);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al actualizar requisición', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/excel', name: 'requisiciones_excel', methods: ['GET'])]
    public function excel(int $id): JsonResponse
    {
        try {
            $req = $this->conn->fetchAssociative("SELECT id, nombre_solicitante, fecha, justificacion, area, sede, urgencia, valor_total FROM requisiciones WHERE id = ?", [$id]);
            if (!$req) return $this->json(['error' => 'No encontrado'], 404);
            $productos = $this->conn->fetchAllAssociative("SELECT nombre, descripcion, cantidad, valor_estimado, compra_tecnologica, ergonomico FROM requisicion_productos WHERE requisicion_id = ?", [$id]);

            // Para simplicidad devolvemos JSON con datos equivalentes.
            // Si quieres generar un XLSX en PHP, instala phpoffice/phpspreadsheet y lo implemento.
            return $this->json(['requisicion' => $req, 'productos' => $productos]);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al generar datos para Excel', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/devolver', name: 'requisiciones_devolver', methods: ['POST'])]
    public function devolver(int $id, Request $request): JsonResponse
    {
        try {
            $exists = $this->conn->fetchOne("SELECT id FROM requisiciones WHERE id = ?", [$id]);
            if (!$exists) return $this->json(['message' => 'Requisición no encontrada'], 404);

            // 🔥 SOLO actualizar el estado de la requisición, NO los productos
            $this->conn->executeStatement("UPDATE requisiciones SET status = ? WHERE id = ?", ['devuelta', $id]);
            // (Opcional) Actualizar aprobaciones a pendiente y visible
            $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET estado = 'pendiente', visible = 0 WHERE requisicion_id = ?", [$id]);
            $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET visible = 1 WHERE requisicion_id = ? AND orden = 1", [$id]);

            // 🔥 NO modificar productos aquí

            return $this->json(['message' => 'Requisición devuelta para corrección', 'requisicionId' => $id]);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al devolver la requisición', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/aprobar-total', name: 'requisiciones_aprobar_total', methods: ['POST'])]
    public function aprobarTotal(int $id): JsonResponse
    {
        try {
            $exists = $this->conn->fetchOne("SELECT id FROM requisiciones WHERE id = ?", [$id]);
            if (!$exists) return $this->json(['message' => 'Requisición no encontrada'], 404);

            // 🔥 SOLO actualizar el estado de la requisición, NO los productos
            $this->conn->executeStatement("UPDATE requisiciones SET status = 'Totalmente Aprobada' WHERE id = ?", [$id]);

            // (Opcional) Actualizar aprobaciones a 'aprobada' y visible=0
            $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET estado = 'aprobada', visible = 0 WHERE requisicion_id = ?", [$id]);

            // (Opcional) recalcular valor_total solo con productos aprobados, pero NO cambiar productos
            $sum = $this->conn->fetchAssociative("SELECT SUM(COALESCE(valor_estimado,0) * COALESCE(cantidad,1)) AS total FROM requisicion_productos WHERE requisicion_id = ? AND aprobado = 'aprobado'", [$id]);
            $nuevoTotal = $sum['total'] ?? 0;
            $this->conn->executeStatement("UPDATE requisiciones SET valor_total = ? WHERE id = ?", [$nuevoTotal, $id]);

            return $this->json(['message' => 'Requisición marcada como aprobada (total)', 'requisicionId' => $id]);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al aprobar totalmente la requisición', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/productos', name: 'requisiciones_replace_productos', methods: ['PUT'])]
    public function replaceProductos(int $id, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $productos = $data['productos'] ?? [];
            $exists = $this->conn->fetchOne("SELECT id FROM requisiciones WHERE id = ?", [$id]);
            if (!$exists) return $this->json(['message' => 'Requisición no encontrada'], 404);

            // delegar al servicio (transacción)
            $nuevoTotal = $this->service->replaceProducts($id, $productos);

            return $this->json(['message' => 'Productos actualizados correctamente', 'nuevoTotal' => $nuevoTotal]);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al actualizar productos', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/aprobaciones', name: 'aprobaciones_list', methods: ['GET'])]
    public function aprobaciones(): JsonResponse
    {
        try {
            $rows = $this->conn->fetchAllAssociative(
                "SELECT id, requisicion_id, area, rol_aprobador, nombre_aprobador, estado, orden, visible, fecha_aprobacion FROM requisicion_aprobaciones ORDER BY requisicion_id, orden"
            );
            return $this->json($rows);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al obtener aprobaciones', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/{id}/progress', name: 'requisition_progress', methods: ['GET'])]
    public function approvalProgress(int $id): JsonResponse
    {
        try {
            $progress = $this->service->getApprovalProgress($id);
            return $this->json($progress);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al obtener progreso', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/requisiciones/aprobador/{nombre}', name: 'api_requisiciones_por_aprobador', methods: ['GET'])]
    public function getPorAprobador(string $nombre, RequisicionesRepository $repo): JsonResponse
    {
        try {
            $data = $repo->findByAprobador($nombre);
            return new JsonResponse($data);
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Error al obtener requisiciones'],
                500
            );
        }
    }

    #[Route('/requisiciones/{id}/aprobacion-usuario', name: 'requisicion_aprobacion_usuario', methods: ['GET'])]
    public function aprobacionUsuario(int $id, Request $request): JsonResponse
    {
        try {
            $userName = $request->headers->get('X-User-Name') ?? null;
            if (!$userName) {
                return $this->json(['message' => 'Usuario no identificado (falta X-User-Name)'], 400);
            }

            // Obtener aprobaciones de la requisición ordenadas
            $aprobaciones = $this->conn->fetchAllAssociative(
                "SELECT id, nombre_aprobador, estado, orden, visible FROM requisicion_aprobaciones WHERE requisicion_id = ? ORDER BY orden ASC",
                [$id]
            );

            if (empty($aprobaciones)) {
                return $this->json(['message' => 'No hay aprobadores asignados'], 404);
            }

            // Buscar la aprobación del usuario actual
            $aprobacionActual = null;
            foreach ($aprobaciones as $a) {
                if (strtolower($a['nombre_aprobador']) === strtolower($userName)) {
                    $aprobacionActual = $a;
                    break;
                }
            }

            if (!$aprobacionActual) {
                return $this->json(['message' => 'Usuario no es aprobador de esta requisición'], 403);
            }

            $estado = $aprobacionActual['estado'] ?? 'pendiente';
            $orden = $aprobacionActual['orden'] ?? null;
            $visible = $aprobacionActual['visible'] ?? 0;

            // Determinar si puede aprobar
            $puedeAprobar = false;
            if (strtolower($estado) === 'pendiente' && ($visible == 1 || $visible === true)) {
                $puedeAprobar = true;
            }

            // Determinar si ya aprobó
            $yaAprobaste = strtolower($estado) === 'aprobada';

            return $this->json([
                'userName' => $userName,
                'requisicionId' => $id,
                'puedeAprobar' => $puedeAprobar,
                'yaAprobaste' => $yaAprobaste,
                'estado' => $estado,
                'orden' => $orden,
                'visible' => $visible,
                'aprobadores' => $aprobaciones
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Error al obtener estado de aprobación', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/productos', name: 'api_productos_list', methods: ['GET'])]
    public function listProductos(Connection $conn): JsonResponse
    {
        $sql = '
            SELECT 
                grupo,
                nombre,
                descripcion,
                cuenta_contable,
                centro_costo
            FROM req_camunda.productos
            ORDER BY grupo, nombre;
        ';
        $rows = $conn->fetchAllAssociative($sql);
        return new JsonResponse($rows, 200);
    }


    #[Route('/requisiciones/{id}/pdf', name: 'requisition_pdf', methods: ['GET'])]
    public function downloadPdf(int $id, Connection $conn): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');

        // ⚠️ Asegúrate que esta ruta existe
        $plantilla = $projectDir . "/templates/plantilla.xlsx";

        // Archivos temporales
        $tempExcel = sys_get_temp_dir() . "/requisicion_{$id}_" . time() . ".xlsx";
        $tempPdf   = sys_get_temp_dir() . "/requisicion_{$id}_" . time() . ".pdf";

        try {
            // 1) Requisición
            $requisicion = $conn->fetchAssociative(
                "SELECT * FROM requisiciones WHERE id = ?",
                [$id]
            );
            if (!$requisicion) {
                throw $this->createNotFoundException("Requisición no encontrada");
            }

            // 2) Productos
            $productos = $conn->fetchAllAssociative(
                "SELECT * FROM requisicion_productos 
                    WHERE requisicion_id = ? 
                    AND aprobado = 'aprobado'",
                [$id]
            );


            // 3) Cargar plantilla Excel
            $reader = new Xlsx();
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($plantilla);

            $sheet = $spreadsheet->getSheetByName("F-SGA-SG-19");
            if ($sheet === null) {
                throw new \Exception("❌ La hoja 'F-SGA-SG-19' no existe en la plantilla.");
            }

            // 🔥 CONFIGURAR PÁGINA PARA QUE TODO QUEPA EN UNA SOLA HOJA
            $sheet->getPageSetup()->setFitToWidth(1);
            $sheet->getPageSetup()->setFitToHeight(1);
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

            // Márgenes muy pequeños
            $sheet->getPageMargins()->setTop(0.2);
            $sheet->getPageMargins()->setBottom(0.2);
            $sheet->getPageMargins()->setLeft(0.2);
            $sheet->getPageMargins()->setRight(0.2);
            $sheet->getPageMargins()->setHeader(0.1);
            $sheet->getPageMargins()->setFooter(0.1);

            // Repetir encabezado en todas las páginas
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 13);
            $sheet->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd('A', 'D');

            // 4) Cabecera
            $sheet->setCellValue("E7", $requisicion["nombre_solicitante"] ?? "N/A");
            $sheet->setCellValue("E8", $requisicion["fecha"] ?? "N/A");
            $sheet->setCellValue("E9", $requisicion["fecha_requerido_entrega"] ?? "N/A");
            $sheet->setCellValue("E10", $requisicion["justificacion"] ?? "N/A");
            $sheet->setCellValue("O7", $requisicion["area"] ?? "N/A");
            $sheet->setCellValue("O8", $requisicion["sede"] ?? "N/A");
            $sheet->setCellValue("K9", $requisicion["urgencia"] ?? "N/A");
            $sheet->setCellValue("T10", ($requisicion["presupuestada"] ? "Sí" : "No"));
            $sheet->setCellValue("T9", $requisicion["tiempoAproximadoGestion"] ?? "N/A");

            // 5) Productos
            $start = 14;
            foreach ($productos as $i => $p) {
                $r = $start + $i;

                $sheet->setCellValue("B$r", $i + 1);
                $sheet->setCellValue("C$r", $p["nombre"]);
                $sheet->setCellValue("F$r", (int)$p["cantidad"]);
                $sheet->setCellValue("G$r", $p["centro_costo"]);
                $sheet->setCellValue("H$r", $p["cuenta_contable"]);
                // 🔥 FIX: Asegurar que el valor sea numérico y establecer ancho de columna
                $valorEstimado = (float)preg_replace('/[^\d.-]/', '', $p["valor_estimado"] ?? 0);
                $sheet->setCellValue("L$r", $valorEstimado);
                $sheet->getColumnDimension('L')->setWidth(25); // Ancho suficiente para números
                $sheet->setCellValue("J$r", $requisicion["presupuestada"] ? "Sí" : "No");
                $sheet->setCellValue("M$r", $p["descripcion"]);
                $sheet->setCellValue("N$r", $p["compraTecnologica"] ? "Sí Aplica" : "No Aplica");
                $sheet->setCellValue("R$r", $p["ergonomico"] ? "Sí Aplica" : "No Aplica");
            }

            // 🔥 NUEVA LÓGICA DE FIRMAS - SIMPLIFICADA (Leer de BD)
            try {
                // 1️⃣ Obtener aprobadores desde requisicion_aprobaciones en orden
                $aprobadores = $conn->fetchAllAssociative(
                    "SELECT nombre_aprobador, rol_aprobador, orden, fecha_aprobacion 
                        FROM requisicion_aprobaciones 
                        WHERE requisicion_id = ? 
                        ORDER BY orden ASC",
                    [$id]
                );


                // 2️⃣ Limpiar celdas de nombres y firmas
                $nameCells = ["D31", "I31", "M31", "O31", "S31"];
                $sigCells = ["D32", "I32", "M32", "O32", "S32"];
                foreach (array_merge($nameCells, $sigCells) as $cell) {
                    $sheet->setCellValue($cell, "");
                }

                // 3️⃣ Escribir solicitante (siempre en D28)
                $sheet->setCellValue("D31", $requisicion["nombre_solicitante"] ?? "N/A");

                // 4️⃣ Asignar aprobadores a las 4 columnas (I, M, O, S) según orden de BD
                $approvalCells = ["I31", "M31", "O31", "S31"];
                foreach ($aprobadores as $idx => $aprobador) {
                    if ($idx < count($approvalCells)) {
                        $sheet->setCellValue($approvalCells[$idx], $aprobador["nombre_aprobador"] ?? "N/A");
                    }
                }

                // 5️⃣ Firmas vacías (permanecer vacías para ser llenadas manualmente)
                foreach ($sigCells as $cell) {
                    $sheet->setCellValue($cell, "");
                }

                // 6️⃣ Validar Gerencia Administrativa y General según monto (si no están en aprobadores)
                $SMLV_local = 1300000;
                $limite_local = $SMLV_local * 10;
                $valorTotalNum = (float)($requisicion["valor_total"] ?? 0);

                $rolesEnAprobadores = array_map(fn($a) => $a["rol_aprobador"], $aprobadores);
                $hasGerAdmin = in_array("gerAdmin", $rolesEnAprobadores, true);
                $hasGerGeneral = in_array("gerGeneral", $rolesEnAprobadores, true);

                if (!$requisicion["presupuestada"] && $valorTotalNum >= $limite_local) {
                    $rolesNeeded = [];
                    if (!$hasGerAdmin) $rolesNeeded[] = "gerAdmin";
                    if (!$hasGerGeneral) $rolesNeeded[] = "gerGeneral";

                    if (!empty($rolesNeeded)) {
                        $placeholders = implode(',', array_fill(0, count($rolesNeeded), '?'));
                        $admins = $conn->fetchAllAssociative(
                            "SELECT nombre, cargo FROM user WHERE cargo IN ($placeholders)",
                            $rolesNeeded
                        );

                        $currentD39 = (string)($sheet->getCell('D36')->getValue() ?? "");
                        $currentM39 = (string)($sheet->getCell('M36')->getValue() ?? "");

                        if (!$currentD39 || $currentD39 === "N/A") {
                            $gerAdmin = array_filter($admins, fn($u) => $u["cargo"] === "gerAdmin");
                            $sheet->setCellValue("D36", !empty($gerAdmin) ? reset($gerAdmin)["nombre"] : "N/A");
                        }
                        if (!$currentM39 || $currentM39 === "N/A") {
                            $gerGeneral = array_filter($admins, fn($u) => $u["cargo"] === "gerGeneral");
                            $sheet->setCellValue("M36", !empty($gerGeneral) ? reset($gerGeneral)["nombre"] : "N/A");
                        }
                    }
                } else {
                    if (!trim($sheet->getCell('D36')->getValue() ?? "")) {
                        $sheet->setCellValue("D36", "");
                    }
                    if (!trim($sheet->getCell('M36')->getValue() ?? "")) {
                        $sheet->setCellValue("M36", "");
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning("⚠️ Error al obtener/aplicar aprobaciones: " . $e->getMessage());
            }

            // Celdas de fecha debajo de la firma (fila 33)
            $dateCells = ["D33", "I33", "M33", "O33", "S33"];

            // Limpiar fechas existentes
            foreach ($dateCells as $cell) {
                $sheet->setCellValue($cell, "");
            }

            // Escribir fechas de aprobación (si existen)
            foreach ($aprobadores as $idx => $aprobador) {
                if ($idx < count($dateCells)) {
                    $fecha = $aprobador["fecha_aprobacion"] ?? null;
                    if ($fecha) {
                        // Formato: AAAA-MM-DD HH:mm
                        $fechaFmt = date("Y-m-d H:i", strtotime($fecha));
                        $sheet->setCellValue($dateCells[$idx], $fechaFmt);
                    }
                }
            }


            // 6) Guardar Excel temporal
            \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xlsx")
                ->save($tempExcel);

            // 7) Convertir con ConvertAPI
            $this->convertUsingConvertAPI($tempExcel, $tempPdf);

            // ✅ VERIFICAR QUE EL PDF EXISTE Y TIENE TAMAÑO
            if (!file_exists($tempPdf) || filesize($tempPdf) === 0) {
                throw new \Exception("El PDF no se generó correctamente o está vacío");
            }

            // 8️⃣ LEER EL ARCHIVO COMPLETAMENTE EN MEMORIA ANTES DE ENVIARLO
            $pdfContent = file_get_contents($tempPdf);
            if (!$pdfContent) {
                throw new \Exception("No se pudo leer el contenido del PDF");
            }

            // 9️⃣ Crear respuesta con el contenido en memoria (evita problemas de Content-Length)
            $response = new Response($pdfContent);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="requisicion_' . $id . '.pdf"');
            $response->headers->set('Content-Length', strlen($pdfContent));
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        } catch (\Throwable $e) {
            // Limpiar archivos temporales en caso de error
            @unlink($tempExcel);
            @unlink($tempPdf);

            $this->logger->error("Error generando PDF: " . $e->getMessage());
            throw $e;
        } finally {
            // Limpiar archivos temporales después de enviar (al salir del request)
            register_shutdown_function(function () use ($tempExcel, $tempPdf) {
                @unlink($tempExcel);
                @unlink($tempPdf);
            });
        }
    }

    private function convertUsingConvertAPI(string $xlsx, string $pdf): void
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $secret = $_ENV['CONVERT_API_SECRET'] ?? '';
        if (!$secret) {
            throw new \Exception("CONVERT_API_SECRET no configurado");
        }

        ConvertApi::setApiCredentials($secret);

        $result = ConvertApi::convert('pdf', [
            'File' => $xlsx,
            'PageOrientation' => 'landscape',
            'PageSize' => 'A4',
            'PdfFitToPage' => true,
            'PdfFitToWidth' => true,
            'PdfFitToHeight' => true,
            'PdfScaleContent' => true,
            'Margins' => 5,
        ], 'xlsx');

        // ✅ Guardar el PDF resultante
        $result->saveFiles($pdf);

        // ⏱ Esperar un poco para asegurar que el archivo se escribió completamente
        sleep(1);

        // ✅ Verificar que el archivo se guardó
        if (!file_exists($pdf)) {
            throw new \Exception("ConvertAPI no generó el archivo PDF en la ubicación esperada");
        }
    }

    #[Route('/requisiciones/{id}/productos', name: 'requisiciones_productos_by_id', methods: ['GET'])]
    public function getProductosByRequisicion(int $id): JsonResponse
    {
        try {
            $productos = $this->conn->fetchAllAssociative(
                "SELECT * FROM requisicion_productos WHERE requisicion_id = ?",
                [$id]
            );
            return $this->json(['productos' => $productos]);
        } catch (Throwable $e) {
            return $this->json(['error' => 'Error al obtener productos', 'detail' => $e->getMessage()], 500);
        }
    }
}
