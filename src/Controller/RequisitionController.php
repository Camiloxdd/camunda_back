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

            $productos = $this->conn->fetchAllAssociative('SELECT id, nombre, descripcion, cantidad, valor_estimado, compra_tecnologica, ergonomico, aprobado, centro_costo, cuenta_contable FROM requisicion_productos WHERE requisicion_id = ?', [$id]);

            // currentUser: si Symfony maneja auth, reemplazar por getUser()
            $userId = $request->headers->get('X-User-Id') ?? null;
            $currentUser = $userId ? $this->conn->fetchAssociative('SELECT id, nombre, cargo, area FROM user WHERE id = ?', [(int)$userId]) : null;

            // obtener progreso de aprobación: simple query agregada
            $approvers = $this->conn->fetchAllAssociative('SELECT id, rol_aprobador, nombre_aprobador, area, estado, orden, visible, fecha_aprobacion FROM requisicion_aprobaciones WHERE requisicion_id = ? ORDER BY orden ASC', [$id]);

            return $this->json([
                'requisicion' => $req,
                'productos' => $productos,
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

            // Obtener aprobador
            $aprobador = $this->conn->fetchAssociative(
                "SELECT rol_aprobador, estado FROM requisicion_aprobaciones WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?",
                [$id, $userName, $userArea]
            );

            if (!$aprobador) {
                return $this->json(['yaAprobaste' => false, 'puedeAprobar' => false]);
            }

            $rolAprobador = $aprobador['rol_aprobador'];
            $estadoAprobador = $aprobador['estado'];

            // Si el aprobador ya está marcado como aprobado, no puede volver a aprobar
            if (strtolower($estadoAprobador) === 'aprobada') {
                return $this->json(['yaAprobaste' => true, 'puedeAprobar' => false]);
            }

            // Obtener productos según el rol del aprobador
            $technoRoles = ['dicTYP', 'gerTyC'];
            $sstRoles = ['dicSST', 'gerSST'];

            // 🔥 CONSULTA MÁS PRECISA: solo productos CON compra_tecnologica=1 O ergonomico=1
            if (in_array($rolAprobador, $technoRoles)) {
                // Solo productos tecnológicos
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id, aprobado FROM requisicion_productos WHERE requisicion_id = ? AND compra_tecnologica = 1",
                    [$id]
                );
            } elseif (in_array($rolAprobador, $sstRoles)) {
                // Solo productos ergonómicos
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id, aprobado FROM requisicion_productos WHERE requisicion_id = ? AND ergonomico = 1",
                    [$id]
                );
            } else {
                // Otros roles (gerAdmin, gerGeneral) no tienen productos específicos
                $productosRelevantes = [];
            }

            // Si no hay productos relevantes, puede aprobar
            if (empty($productosRelevantes)) {
                return $this->json(['yaAprobaste' => false, 'puedeAprobar' => true]);
            }

            // Verificar si todos los productos relevantes tienen estado (aprobado o rechazado)
            $todosConEstado = true;
            foreach ($productosRelevantes as $p) {
                if ($p['aprobado'] === null || $p['aprobado'] === '') {
                    $todosConEstado = false;
                    break;
                }
            }

            return $this->json([
                'yaAprobaste' => $todosConEstado,
                'puedeAprobar' => !$todosConEstado
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
            $decisiones = $data['decisiones'] ?? [];

            $userName = $request->headers->get('X-User-Name');
            $userArea = $request->headers->get('X-User-Area');

            $this->logger->info("APROBADOR => $userName | ÁREA => $userArea");

            if (!is_array($decisiones)) {
                return $this->json(['message' => 'Formato inválido: decisiones debe ser un array'], 400, $corsHeaders);
            }

            $this->conn->beginTransaction();

            // 1️⃣ ACTUALIZAR PRODUCTOS
            foreach ($decisiones as $d) {
                $productoId = $d['id'] ?? null;
                $aprobado = !empty($d['aprobado']);
                $fechaAprobado = $d['fecha_aprobado'] ?? null;

                $this->conn->executeStatement(
                    "UPDATE requisicion_productos
                    SET aprobado = ?, fecha_aprobado = ?
                    WHERE id = ? AND requisicion_id = ?",
                    [
                        $aprobado ? 'aprobado' : 'rechazado',
                        $aprobado ? ($fechaAprobado ?? (new \DateTime())->format('Y-m-d H:i:s')) : null,
                        $productoId,
                        $id
                    ]
                );
            }

            // 2️⃣ CALCULAR NUEVO TOTAL SOLO DE PRODUCTOS APROBADOS
            $sum = $this->conn->fetchAssociative(
                "SELECT SUM(COALESCE(valor_estimado, 0) * COALESCE(cantidad, 1)) AS nuevo_total
                FROM requisicion_productos
                WHERE requisicion_id = ? AND (aprobado = 'aprobado' OR aprobado = 1)",
                [$id]
            );
            $nuevoTotal = $sum['nuevo_total'] ?? 0;

            $this->conn->executeStatement(
                "UPDATE requisiones SET valor_total = ? WHERE id = ?",
                [$nuevoTotal, $id]
            );

            // 3️⃣ OBTENER DATOS DEL APROBADOR
            $aprobador = $this->conn->fetchAssociative(
                "SELECT rol_aprobador, orden FROM requisicion_aprobaciones WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?",
                [$id, $userName, $userArea]
            );

            if (!$aprobador) {
                $this->conn->rollBack();
                return $this->json(
                    ['message' => 'No se encontró aprobación correspondiente al usuario actual.'],
                    404,
                    $corsHeaders
                );
            }

            $rolAprobador = $aprobador['rol_aprobador'];
            $ordenActual = $aprobador['orden'];

            // 4️⃣ OBTENER SOLO LOS PRODUCTOS RELEVANTES (IGUAL QUE EN verificarAprobacion)
            $technoRoles = ['dicTYP', 'gerTyC'];
            $sstRoles = ['dicSST', 'gerSST'];

            $productosRelevantes = [];
            if (in_array($rolAprobador, $technoRoles)) {
                // Solo productos tecnológicos
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id, aprobado FROM requisicion_productos WHERE requisicion_id = ? AND compra_tecnologica = 1",
                    [$id]
                );
            } elseif (in_array($rolAprobador, $sstRoles)) {
                // Solo productos ergonómicos
                $productosRelevantes = $this->conn->fetchAllAssociative(
                    "SELECT id, aprobado FROM requisicion_productos WHERE requisicion_id = ? AND ergonomico = 1",
                    [$id]
                );
            }

            // 5️⃣ SI NO HAY PRODUCTOS RELEVANTES, MARCAR COMO APROBADA
            if (empty($productosRelevantes)) {
                $this->conn->executeStatement(
                    "UPDATE requisicion_aprobaciones SET estado = 'aprobada', fecha_aprobacion = NOW() WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?",
                    [$id, $userName, $userArea]
                );

                $this->conn->executeStatement(
                    "UPDATE requisicion_aprobaciones SET visible = TRUE WHERE requisicion_id = ? AND orden = ?",
                    [$id, $ordenActual + 1]
                );

                $this->conn->commit();
                return $this->json([
                    'message' => 'Aprobador marcado como aprobada (sin productos relevantes).',
                    'nuevo_total' => $nuevoTotal,
                    'pendientes' => 0
                ], 200, $corsHeaders);
            }

            // 6️⃣ VERIFICAR SI TODOS LOS PRODUCTOS RELEVANTES TIENEN ESTADO
            $todosConEstado = true;
            foreach ($productosRelevantes as $p) {
                if ($p['aprobado'] === null || $p['aprobado'] === '') {
                    $todosConEstado = false;
                    break;
                }
            }

            // 7️⃣ SI TODOS LOS RELEVANTES TIENEN ESTADO → MARCAR APROBADOR COMO APROBADA
            if ($todosConEstado) {
                $this->conn->executeStatement(
                    "UPDATE requisicion_aprobaciones SET estado = 'aprobada', fecha_aprobacion = NOW() WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?",
                    [$id, $userName, $userArea]
                );

                // 8️⃣ ACTIVAR SIGUIENTE APROBADOR
                $this->conn->executeStatement(
                    "UPDATE requisicion_aprobaciones SET visible = TRUE WHERE requisicion_id = ? AND orden = ?",
                    [$id, $ordenActual + 1]
                );
            }

            // 9️⃣ CONTAR TOTALES PARA DETERMINAR ESTADO FINAL
            $cnt = $this->conn->fetchAssociative(
                "SELECT COUNT(*) AS cnt FROM requisicion_productos WHERE requisicion_id = ? AND (aprobado = 'aprobado' OR aprobado = 1)",
                [$id]
            );
            $approvedCount = (int)($cnt['cnt'] ?? 0);

            $cntRechazados = $this->conn->fetchAssociative(
                "SELECT COUNT(*) AS cnt FROM requisicion_productos WHERE requisicion_id = ? AND aprobado = 'rechazado'",
                [$id]
            );
            $rejectedCount = (int)($cntRechazados['cnt'] ?? 0);

            $totalProds = $this->conn->fetchAssociative(
                "SELECT COUNT(*) AS total FROM requisicion_productos WHERE requisicion_id = ?",
                [$id]
            );
            $totalProductos = (int)($totalProds['total'] ?? 0);

            // 🔟 SI TODOS LOS PRODUCTOS FUERON RECHAZADOS → RECHAZAR TODO
            if ($approvedCount === 0 && $rejectedCount === $totalProductos && $totalProductos > 0) {
                $this->conn->executeStatement(
                    "UPDATE requisicion_aprobaciones SET estado = 'rechazada', visible = FALSE WHERE requisicion_id = ?",
                    [$id]
                );
                $this->conn->executeStatement(
                    "UPDATE requisiones SET status = 'rechazada', valor_total = 0 WHERE id = ?",
                    [$id]
                );
                $this->conn->commit();
                return $this->json([
                    'message' => 'Requisición rechazada completamente. No quedan ítems aprobados.',
                    'nuevo_total' => 0,
                    'pendientes' => 0
                ], 200, $corsHeaders);
            }

            // 1️⃣1️⃣ VER SI QUEDAN APROBACIONES PENDIENTES
            $pend = $this->conn->fetchAssociative(
                "SELECT COUNT(*) AS cnt FROM requisicion_aprobaciones WHERE requisicion_id = ? AND estado = 'pendiente'",
                [$id]
            );
            $pendientesCount = (int)($pend['cnt'] ?? 0);

            // 1️⃣2️⃣ SI NO QUEDAN PENDIENTES Y HAY APROBADOS → MARCAR REQUISICIÓN COMO APROBADA
            if ($pendientesCount === 0 && $approvedCount > 0) {
                $this->conn->executeStatement(
                    "UPDATE requisiones SET status = 'aprobada' WHERE id = ?",
                    [$id]
                );
            }

            $this->conn->commit();

            return $this->json([
                'message' => 'Operación registrada correctamente.',
                'nuevo_total' => $nuevoTotal,
                'pendientes' => $pendientesCount
            ], 200, $corsHeaders);
        } catch (\Throwable $e) {
            if ($this->conn->isTransactionActive()) {
                $this->conn->rollBack();
            }

            return $this->json([
                'message' => 'Error al procesar ítems',
                'error' => $e->getMessage()
            ], 500, $corsHeaders);
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
                "UPDATE requisiones SET nombre_solicitante=?, fecha=?, justificacion=?, area=?, sede=?, urgencia=?, presupuestada=? WHERE id=?",
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

            $this->conn->executeStatement("UPDATE requisiciones SET status = ? WHERE id = ?", ['devuelta', $id]);
            $this->conn->executeStatement("UPDATE requisicion_productos SET aprobado = NULL WHERE requisicion_id = ?", [$id]);
            $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET estado = 'pendiente', visible = 0 WHERE requisicion_id = ?", [$id]);
            $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET visible = 1 WHERE requisicion_id = ? AND orden = 1", [$id]);

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

            $this->conn->executeStatement("UPDATE requisicion_productos SET aprobado = 'aprobado' WHERE requisicion_id = ?", [$id]);
            $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET estado = 'aprobada', visible = 0 WHERE requisicion_id = ?", [$id]);
            $this->conn->executeStatement("UPDATE requisiones SET status = 'Totalmente Aprobada' WHERE id = ?", [$id]);

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
            id,
            nombre,
            descripcion,
            cuenta_contable,
            centro_costo,
            es_tecnologico,
            CASE WHEN es_tecnologico = 1 THEN 0 ELSE 1 END AS ergonomico
            FROM productos
        ';
        $rows = $conn->fetchAllAssociative($sql);
        return new JsonResponse($rows, 200);
    }


    #[Route('/requisiciones/{id}/pdf', name: 'requisition_pdf', methods: ['GET'])]
    public function downloadPdf(int $id, Connection $conn): BinaryFileResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');

        // ⚠️ Asegúrate que esta ruta existe
        $plantilla = $projectDir . "/templates/plantilla.xlsx";

        // Archivos temporales
        $tempExcel = sys_get_temp_dir() . "/requisicion_{$id}.xlsx";
        $tempPdf   = sys_get_temp_dir() . "/requisicion_{$id}.pdf";

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
            "SELECT * FROM requisicion_productos WHERE requisicion_id = ?",
            [$id]
        );

        // 3) Cargar plantilla Excel
        $reader = new Xlsx();
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($plantilla);

        // ⚠️ Si la hoja no existe marcaba el error setCellValue null
        $sheet = $spreadsheet->getSheetByName("F-SGA-SG-19");
        if ($sheet === null) {
            throw new \Exception("❌ La hoja 'F-SGA-SG-19' no existe en la plantilla.");
        }

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
            $sheet->setCellValue("L$r", preg_replace('/[^\d.-]/', '', $p["valor_estimado"]));
            $sheet->setCellValue("J$r", $requisicion["presupuestada"] ? "Sí" : "No");
            $sheet->setCellValue("M$r", $p["descripcion"]);
            $sheet->setCellValue("N$r", $p["compra_tecnologica"] ? "Sí Aplica" : "No Aplica");
            $sheet->setCellValue("R$r", $p["ergonomico"] ? "Sí Aplica" : "No Aplica");
        }

        // 6) Guardar Excel temporal
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xlsx")
            ->save($tempExcel);

        // 7) Convertir con ConvertAPI oficial
        $this->convertUsingConvertAPI($tempExcel, $tempPdf);

        // 8) Descargar
        $response = new BinaryFileResponse($tempPdf);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            "requisicion_{$id}.pdf"
        );

        return $response;
    }


    private function convertUsingConvertAPI(string $xlsx, string $pdf): void
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $secret = $_ENV['CONVERT_API_SECRET'] ?? '';
        if (!$secret) {
            throw new \Exception("CONVERT_API_SECRET no configurado");
        }

        // Configurar SDK
        ConvertApi::setApiCredentials($secret);

        // Convertir archivo
        $result = ConvertApi::convert('pdf', [
            'File' => $xlsx,
            'PageOrientation' => 'landscape',
            'AutoConvert' => 'true',
        ], 'xlsx');

        // Guardar PDF
        $result->getFile()->save($pdf);
    }
}
