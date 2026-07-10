<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<Network>
 */
class NetworkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Network::class);
    }

    public function save(Network $network, bool $flush = false): void
    {
        $this->getEntityManager()->persist($network);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Network $network, bool $flush = false): void
    {
        $this->getEntityManager()->remove($network);
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
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('n')
            ->orderBy('n.' . $sort, $order)
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
     * @return array<int, Network>
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

        $ids = $conn->fetchFirstColumn(
            'SELECT id FROM `Network` WHERE MBRIntersects(geometry, ST_GeomFromText(:bboxWkt, 4326))',
            ['bboxWkt' => $bboxWkt]
        );

        return $ids === [] ? [] : $this->findBy(['id' => $ids]);
    }
}
