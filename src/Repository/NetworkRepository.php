<?php

namespace App\Repository;

use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * @return Network[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Network[]
     */
    public function findByOperator(string $operator): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.operator = :operator')
            ->setParameter('operator', $operator)
            ->orderBy('n.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Fetches networks with their access points eagerly to avoid N+1 queries.
     *
     * @return Network[]
     */
    public function findAllWithAccessPoints(): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.accessPoints', 'ap')
            ->addSelect('ap')
            ->orderBy('n.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
