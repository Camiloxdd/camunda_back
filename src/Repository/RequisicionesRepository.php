<?php

namespace App\Repository;

use App\Entity\Requisiciones;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Requisiciones>
 */
class RequisicionesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Requisiciones::class);
    }

    public function findByAprobador(string $nombre): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                r.id AS requisicion_id,
                r.valor_total,
                r.status,
                r.nombre_solicitante,
                r.fecha,
                r.area,
                r.sede,
                r.urgencia,
                r.justificacion,
                a.estado AS estado_aprobacion
            FROM requisiciones r
            INNER JOIN requisicion_aprobaciones a 
                ON r.id = a.requisicion_id
            WHERE a.nombre_aprobador = :nombre
            ORDER BY r.fecha DESC
        ";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['nombre' => $nombre]);

        return $result->fetchAllAssociative();
    }
}
