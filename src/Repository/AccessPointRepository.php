<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessPoint;
use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccessPoint>
 */
class AccessPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessPoint::class);
    }

    public function save(AccessPoint $accessPoint, bool $flush = false): void
    {
        $this->getEntityManager()->persist($accessPoint);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AccessPoint $accessPoint, bool $flush = false): void
    {
        $this->getEntityManager()->remove($accessPoint);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Searches for Networks based on provided filter and optional search term.
     *
     */
    public function searchWithFilter(
        string $sort,
        string $order,
        ?string $query,
        int $page,
        int $count,
        Network $network,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('n')
            ->orderBy('n.' . $sort, $order)
            ->Where('n.network = :network')
            ->setParameter('network', $network)
            ->setFirstResult(($page - 1) * $count)
            ->setMaxResults($count);

        if ($query !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('n.name', ':query'),
                )
            )->setParameter('query', '%' . $query . '%');
        }

        return $qb;
    }

    public function findIntersectingBbox(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $bboxWkt = sprintf(
            'POLYGON((%1$F %2$F, %1$F %4$F, %3$F %4$F, %3$F %2$F, %1$F %2$F))',
            $minLat, $minLng, $maxLat, $maxLng
        );

        $ids = $conn->fetchFirstColumn(
            'SELECT id FROM `AccessPoint` WHERE MBRContains(ST_GeomFromText(:bboxWkt, 4326), location)',
            ['bboxWkt' => $bboxWkt]
        );

        return $ids === [] ? [] : $this->findBy(['id' => $ids]);
    }

    public function findByNetworkWithCoordinates(Network $network): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT 
            id, 
            name, 
            ssid, 
            ST_X(location) AS lng, 
            ST_Y(location) AS lat 
        FROM AccessPoint
        WHERE network_id = :networkId 
          AND location IS NOT NULL
    ';

        return $conn->fetchAllAssociative($sql, [
            'networkId' => $network->getId()
        ]);
    }
}
