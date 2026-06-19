<?php

namespace App\Repository;

use App\Entity\AccessPoint;
use App\Entity\Network;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
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
}
