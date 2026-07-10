<?php

namespace App\Twig\Components;

use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Repository\AccessPointRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
class AccessPointsSearchForm
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $filter = 'all';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $count = 7;

    #[LiveProp(writable: true)]
    public string $sort = 'createdAt';

    #[LiveProp(writable: true)]
    public string $order = 'desc';

    /** @var Paginator<AccessPoint>|null */
    private ?Paginator $cachedAccessPoints = null;

    /** @var array<string, int>|null */
    private ?array $cachedCounts = null;

    #[LiveProp]
    public Network $network;

    public function __construct(
        private readonly AccessPointRepository $accessPointRepository,
    ) {
    }

    /**
     * @return Paginator<AccessPoint>
     */
    #[ExposeInTemplate]
    public function getAccessPoints(): Paginator
    {
        if (!$this->cachedAccessPoints instanceof Paginator) {
            $this->cachedAccessPoints = new Paginator($this->getQueryBuilder());
        }

        return $this->cachedAccessPoints;
    }

    /**
     * @return array<int, array>
     */
    public function getAccessPointsData(): array
    {
        $paginator = $this->getAccessPoints();
        $entities = iterator_to_array($paginator);

        if ($entities === []) {
            return [];
        }

        $ids = array_values(array_map(static fn(AccessPoint $ap) => $ap->getId(), $entities));

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $conn = $this->accessPointRepository->getEntityManager()->getConnection();

        $sql = "SELECT id, 
        ST_X(location) as lng, 
        ST_Y(location) as lat 
        FROM AccessPoint 
        WHERE id IN ($placeholders) 
        AND location IS NOT NULL";

        $stmt = $conn->executeQuery($sql, $ids);
        $coords = $stmt->fetchAllAssociative();

        $coordMap = [];

        foreach ($coords as $row) {
            $coordMap[$row['id']] = [
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng']
            ];
        }

        $data = [];
        foreach ($entities as $ap) {
            $data[] = [
                'entity' => $ap,
                'lat' => $coordMap[$ap->getId()]['lat'] ?? null,
                'lng' => $coordMap[$ap->getId()]['lng'] ?? null,
            ];
        }

        return $data;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->accessPointRepository->searchWithFilter(
            $this->sort,
            $this->order,
            $this->query ?: null,
            $this->page,
            $this->count,
            $this->network
        );
    }

    /**
     * @return array<string, int>
     */
    #[ExposeInTemplate]
    public function getAccessPointCounts(): array
    {
        if ($this->cachedCounts === null) {
            $this->cachedCounts = [
                'all' => $this->accessPointRepository->count(['network' => $this->network]),
            ];
        }

        return $this->cachedCounts;
    }

    #[ExposeInTemplate]
    public function getTotalPages(): int
    {
        return (int)ceil(count($this->getAccessPoints()) / $this->count);
    }

    #[LiveAction]
    public function changeSort(#[LiveArg] string $field): void
    {
        if ($this->sort === $field) {
            $this->order = $this->order === 'desc' ? 'asc' : 'desc';
        } else {
            $this->sort = $field;
            $this->order = 'desc';
        }
    }
}
