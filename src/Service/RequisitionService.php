<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Throwable;

class RequisitionService
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    // Ejemplo: obtener aprobadores por roles
    public function fetchUsersByRoles(array $roles): array
    {
        if (empty($roles)) return [];
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT nombre, cargo AS rol, area FROM user WHERE cargo IN ($placeholders)";
        return $this->conn->fetchAllAssociative($sql, $roles);
    }

    public function getApprovalProgress(int $requisicionId): array
    {
        $reqVal = $this->conn->fetchAssociative("SELECT valor_total FROM requisiciones WHERE id = ?", [$requisicionId]);
        $valorRequisicion = (float)($reqVal['valor_total'] ?? 0);
        $THRESHOLD = 10000000;
        $requiredMinimum = $valorRequisicion >= $THRESHOLD ? 4 : 2;

        $counts = $this->conn->fetchAssociative(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) AS aprobadas, SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes, MAX(CASE WHEN estado = 'aprobada' THEN orden ELSE NULL END) AS lastApprovedOrder FROM requisicion_aprobaciones WHERE requisicion_id = ?",
            [$requisicionId]
        );

        $totalApprovals = (int)($counts['total'] ?? 0);
        $approvedCount = (int)($counts['aprobadas'] ?? 0);
        $pendientesCount = (int)($counts['pendientes'] ?? 0);
        $lastApprovedOrder = $counts['lastApprovedOrder'] ?? null;
        $nextOrder = $pendientesCount > 0 ? (($lastApprovedOrder === null) ? 1 : ($lastApprovedOrder + 1)) : null;

        $approvers = $this->conn->fetchAllAssociative("SELECT id, rol_aprobador, nombre_aprobador, area, estado, orden, visible, fecha_aprobacion FROM requisicion_aprobaciones WHERE requisicion_id = ? ORDER BY orden ASC", [$requisicionId]);

        return [
            'valorRequisicion' => $valorRequisicion,
            'requiredMinimum' => $requiredMinimum,
            'totalApprovals' => $totalApprovals,
            'approvedCount' => $approvedCount,
            'pendientesCount' => $pendientesCount,
            'lastApprovedOrder' => $lastApprovedOrder,
            'nextOrder' => $nextOrder,
            'approvers' => $approvers
        ];
    }

    /**
     * Reemplaza productos de una requisición dentro de una transacción y recalcula valor_total.
     * Retorna nuevo total.
     */
    public function replaceProducts(int $requisicionId, array $productos): float
    {
        $conn = $this->conn;
        try {
            $conn->beginTransaction();
            $conn->executeStatement("DELETE FROM requisicion_productos WHERE requisicion_id = ?", [$requisicionId]);

            if (!empty($productos)) {
                foreach ($productos as $p) {
                    $conn->executeStatement(
                        "INSERT INTO requisicion_productos (requisicion_id, nombre, cantidad, descripcion, compra_tecnologica, ergonomico, valor_estimado, centro_costo, cuenta_contable, aprobado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
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
                        ]
                    );
                }
            }

            $sum = $conn->fetchAssociative("SELECT SUM(COALESCE(valor_estimado,0) * COALESCE(cantidad,1)) AS total FROM requisicion_productos WHERE requisicion_id = ?", [$requisicionId]);
            $nuevoTotal = (float)($sum['total'] ?? 0);
            $conn->executeStatement("UPDATE requisiciones SET valor_total = ? WHERE id = ?", [$nuevoTotal, $requisicionId]);

            $conn->commit();
            return $nuevoTotal;
        } catch (Throwable $e) {
            if ($conn->isTransactionActive()) $conn->rollBack();
            throw $e;
        }
    }
}
