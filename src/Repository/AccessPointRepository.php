<?php

namespace App\Repository;

use App\Entity\AccessPoint;
use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
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
     * @throws \JsonException
     * @return AccessPoint[]
     */
    public function findWithinRadius(float $lat, float $lng, float $radiusKm): array
    {
        return $this->getEntityManager()->createNativeQuery(
            'SELECT * FROM AccessPoint
         WHERE ST_Distance_Sphere(
             ST_GeomFromGeoJSON(location),
             ST_GeomFromGeoJSON(:point)
         ) <= :radius',
            new ResultSetMapping()
        )
            ->setParameter(
                'point',
                json_encode([
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat]
                ], JSON_THROW_ON_ERROR)
            )
            ->setParameter('radius', $radiusKm * 1000)
            ->getResult();
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
}
