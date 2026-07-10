<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessPoint;
use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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

    /**
     * @param float $minLat
     * @param float $minLng
     * @param float $maxLat
     * @param float $maxLng
     * @return array<int, array{id: int|string, name: string|null, ssid: string|null, lat: float|string, lng: float|string}>
     * @throws Exception
     */
    public function findIntersectingBbox(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $bboxWkt = sprintf(
            'POLYGON((%1$F %2$F, %1$F %4$F, %3$F %4$F, %3$F %2$F, %1$F %2$F))',
            $minLat,
            $minLng,
            $maxLat,
            $maxLng
        );

        $sql = '
                SELECT 
                    id, 
                    name, 
                    ssid, 
                    ST_Y(location) AS lng, 
                    ST_X(location) AS lat 
                FROM `AccessPoint`
                WHERE MBRContains(ST_GeomFromText(:bboxWkt, 4326), location)
            ';

        $results = $conn->fetchAllAssociative($sql, ['bboxWkt' => $bboxWkt]);

        /** @var array<int, array{id: int|string, name: string|null, ssid: string|null, lat: float|string, lng: float|string}> $results */
        return $results;
    }

    /**
     * @param Network $network
     * @return array<int, array{id: int|string, name: string|null, ssid: string|null, lat: float|string, lng: float|string}>
     */
    public function findByNetworkWithCoordinates(Network $network): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
                SELECT 
                    id, 
                    name, 
                    ssid, 
                    ST_Y(location) AS lng, 
                    ST_X(location) AS lat 
                FROM AccessPoint
                WHERE network_id = :networkId 
                  AND location IS NOT NULL
            ';

        $results = $conn->fetchAllAssociative($sql, [
            'networkId' => $network->getId()
        ]);

        /** @var array<int, array{id: int|string, name: string|null, ssid: string|null, lat: float|string, lng: float|string}> $results */
        return $results;
    }

    /**
     * @param array<int> $ids
     * @return array<int, array{lat: float, lng: float}>
     * @throws Exception
     */
    public function findCoordinatesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT id, ST_X(location) as lng, ST_Y(location) as lat
            FROM AccessPoint
            WHERE id IN ($placeholders)
              AND location IS NOT NULL";

        $rows = $conn->executeQuery($sql, $ids)->fetchAllAssociative();

        $coordMap = [];
        foreach ($rows as $row) {
            $coordMap[(int) $row['id']] = [
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
            ];
        }

        return $coordMap;
    }
}
