<?php

namespace App\Repository;

use App\Entity\CoveragePolygon;
use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CoveragePolygon>
 */
class CoveragePolygonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoveragePolygon::class);
    }

    public function save(CoveragePolygon $polygon, bool $flush = false): void
    {
        $this->getEntityManager()->persist($polygon);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CoveragePolygon $polygon, bool $flush = false): void
    {
        $this->getEntityManager()->remove($polygon);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return CoveragePolygon[]
     */
    public function findByNetwork(Network $network): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.network = :network')
            ->setParameter('network', $network)
            ->orderBy('cp.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
