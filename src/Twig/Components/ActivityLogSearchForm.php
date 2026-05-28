<?php

namespace App\Twig\Components;

use App\Enum\AnalyticalEventType;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ActivityLogSearchForm extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $filter = 'all';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $count = 10;

    #[LiveProp(writable: true)]
    public string $sort = 'event_datetime';

    #[LiveProp(writable: true)]
    public string $order = 'desc';

    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    public function getSuggestions(): array
    {
        if (strlen($this->query) < 2) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(static fn($case) => $case->value, AnalyticalEventType::cases()),
                fn($value) => str_contains(strtolower($value), strtolower($this->query))
            )
        );
    }

    public function getLogs(): array
    {
        $all = $this->eventRepository->searchWithFilter(
            $this->filter,
            $this->sort,
            $this->order,
            $this->query ?: null
        );

        $offset = ($this->page - 1) * $this->count;
        return array_slice($all, $offset, $this->count);
    }

    public function getTotalLogs(): int
    {
        return count(
            $this->eventRepository->searchWithFilter(
                $this->filter,
                $this->sort,
                $this->order,
                $this->query ?: null
            )
        );
    }

    public function getTotalPages(): int
    {
        return (int)ceil($this->getTotalLogs() / $this->count);
    }

    public function getEventCounts(): array
    {
        return $this->eventRepository->countByEventGroup();
    }
}
