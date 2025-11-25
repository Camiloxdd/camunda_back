<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Connection;
use App\Repository\RequisicionesRepository;
use App\Service\RequisitionService;
use Throwable;

#[Route('/api', name: 'api_')]
class RequisitionController extends AbstractController
{
    private Connection $conn;
    private RequisitionService $service;

    public function __construct(Connection $connection, RequisitionService $service)
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

    #[Route('/requisiciones/{id}/aprobar-items', name: 'requisition_aprobar_items', methods: ['PUT', 'OPTIONS'])]
    public function aprobarItems(int $id, Request $request): JsonResponse
    {
        $corsHeaders = $this->getCorsHeaders();
        // Responder preflight OPTIONS
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->json(null, 200, $corsHeaders);
        }

        try {
            $data = $request->toArray();
            $decisiones = $data['decisiones'] ?? [];
            // usuario actual (cabecera)
            $userName = $request->headers->get('X-User-Name') ?? null;
            $userArea = $request->headers->get('X-User-Area') ?? null;

            if (!is_array($decisiones)) {
                return $this->json(['message' => 'Formato inválido: decisiones debe ser un array'], 400, $corsHeaders);
            }

            $this->conn->beginTransaction();

            // actualizar cada producto
            foreach ($decisiones as $d) {
                $productoId = $d['id'] ?? null;
                $aprobado = !empty($d['aprobado']);
                $fechaAprobado = $d['fecha_aprobado'] ?? null;
                $this->conn->executeStatement('UPDATE requisicion_productos SET aprobado = ?, fecha_aprobado = ? WHERE id = ? AND requisicion_id = ?', [$aprobado ? 'aprobado' : 'rechazado', $aprobado ? ($fechaAprobado ?? (new \DateTime())->format('Y-m-d H:i:s')) : null, $productoId, $id]);
            }

            // recalcular valor total con aprobados
            $sum = $this->conn->fetchAssociative('SELECT SUM(COALESCE(valor_estimado,0) * COALESCE(cantidad,1)) AS nuevo_total FROM requisicion_productos WHERE requisicion_id = ? AND (aprobado = \'aprobado\' OR aprobado = 1)', [$id]);
            $nuevoTotal = $sum['nuevo_total'] ?? 0;
            $this->conn->executeStatement('UPDATE requisiones SET valor_total = ? WHERE id = ?', [$nuevoTotal, $id]);

            // contar aprobados
            $cnt = $this->conn->fetchAssociative('SELECT COUNT(*) AS cnt FROM requisicion_productos WHERE requisicion_id = ? AND (aprobado = \'aprobado\' OR aprobado = 1)', [$id]);
            $approvedCount = (int)($cnt['cnt'] ?? 0);

            if ($approvedCount === 0) {
                // marcar rechazadas las aprobaciones y requisicion
                $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET estado = 'rechazada', visible = 0 WHERE requisicion_id = ?", [$id]);
                $this->conn->executeStatement("UPDATE requisiones SET status = 'rechazada', valor_total = 0 WHERE id = ?", [$id]);

                // opcional: cancelar proceso Camunda si corresponde (no implementado aquí)
                $this->conn->commit();
                return $this->json(['message' => 'Requisición rechazada completamente', 'nuevo_total' => 0, 'pendientes' => 0], 200, $corsHeaders);
            }

            // marcar aprobación del usuario actual y activar siguiente
            $res = $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET estado = 'aprobada', visible = 0, fecha_aprobacion = NOW() WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?", [$id, $userName, $userArea]);
            if ($res === 0) {
                $this->conn->rollBack();
                return $this->json(['message' => 'No se encontró aprobación correspondiente al usuario actual.'], 404, $corsHeaders);
            }

            $actual = $this->conn->fetchAssociative("SELECT orden FROM requisicion_aprobaciones WHERE requisicion_id = ? AND nombre_aprobador = ? AND area = ?", [$id, $userName, $userArea]);
            $ordenActual = $actual['orden'] ?? null;
            if ($ordenActual !== null) {
                $this->conn->executeStatement("UPDATE requisicion_aprobaciones SET visible = 1 WHERE requisicion_id = ? AND orden = ?", [$id, $ordenActual + 1]);
            }

            $pendientes = $this->conn->fetchAssociative("SELECT COUNT(*) AS cnt FROM requisicion_aprobaciones WHERE requisicion_id = ? AND estado = 'pendiente'", [$id]);
            $pendientesCount = (int)($pendientes['cnt'] ?? 0);

            if ($pendientesCount === 0 && $approvedCount > 0) {
                $this->conn->executeStatement("UPDATE requisiciones SET status = 'aprobada' WHERE id = ?", [$id]);
            }

            $this->conn->commit();

            return $this->json(['message' => 'Operación registrada correctamente', 'nuevo_total' => $nuevoTotal, 'pendientes' => $pendientesCount], 200, $corsHeaders);
        } catch (Throwable $e) {
            if ($this->conn->isTransactionActive()) $this->conn->rollBack();
            return $this->json(['message' => 'Error al procesar ítems', 'error' => $e->getMessage()], 500, $corsHeaders);
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
            $this->conn->executeStatement("UPDATE requisiciones SET status = 'Totalmente Aprobada' WHERE id = ?", [$id]);

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
}
