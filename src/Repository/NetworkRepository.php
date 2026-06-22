<?php

namespace App\Repository;

use App\Entity\Network;
use App\Enum\UserVerificationStatus;
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

    /**
     * Searches for users based on provided filter and optional search term.
     *
     * Filters out admin/super admin roles.
     * Applies verification / banned filters.
     * Excludes soft-deleted users.
     */
    public function searchWithFilter(
        string $filter,
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
                    $qb->expr()->like('n.email', ':query'),
                    $qb->expr()->like('n.uuid', ':query'),
                    $qb->expr()->like('n.first_name', ':query'),
                    $qb->expr()->like('n.last_name', ':query'),
                    $qb->expr()->like('n.phoneNumber', ':query'),
                )
            )->setParameter('query', '%' . $query . '%');
        }

        return $qb;
    }
}
