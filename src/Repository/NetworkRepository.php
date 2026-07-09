<?php

namespace App\Repository;

use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

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

    public function findIntersectingBbox(
        float $minLat,
        float $minLng,
        float $maxLat,
        float $maxLng,
    ): array {
        return $this->createQueryBuilder('n')
            ->andWhere('n.minLat <= :maxLat')
            ->andWhere('n.maxLat >= :minLat')
            ->andWhere('n.minLng <= :maxLng')
            ->andWhere('n.maxLng >= :minLng')
            ->setParameter('minLat', $minLat)
            ->setParameter('maxLat', $maxLat)
            ->setParameter('minLng', $minLng)
            ->setParameter('maxLng', $maxLng)
            ->getQuery()
            ->getResult();
    }
}
