<?php

namespace App\Repository;

use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Enum\AccessPointType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * @return AccessPoint[]
     */
    public function findByNetwork(Network $network): array
    {
        return $this->createQueryBuilder('ap')
            ->andWhere('ap.network = :network')
            ->setParameter('network', $network)
            ->orderBy('ap.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AccessPoint[]
     */
    public function findByType(AccessPointType $type): array
    {
        return $this->createQueryBuilder('ap')
            ->andWhere('ap.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AccessPoint[]
     */
    public function findByNetworkAndType(Network $network, AccessPointType $type): array
    {
        return $this->createQueryBuilder('ap')
            ->andWhere('ap.network = :network')
            ->andWhere('ap.type = :type')
            ->setParameter('network', $network)
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }
}
