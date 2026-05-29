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

    #[LiveProp(writable: true)]
    public string $startDate = '';

    #[LiveProp(writable: true)]
    public string $endDate = '';

    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    public function getSuggestions(): array
    {
        if (strlen($this->query) < 2) {
            return [];
        }

        $suggestions = [];
        foreach (AnalyticalEventType::cases() as $case) {
            $label = $case->getLabel();
            // Match against both the label and the raw value
            if (
                str_contains(strtolower($label), strtolower($this->query)) ||
                str_contains(strtolower($case->value), strtolower($this->query))
            ) {
                $suggestions[] = [
                    'value' => $case->value,
                    'label' => $label,
                ];
            }
        }

        return $suggestions;
    }

    private function resolvedQuery(): ?string
    {
        if (!$this->query) {
            return null;
        }

        // Check if the query matches an enum label and resolve to raw value
        foreach (AnalyticalEventType::cases() as $case) {
            if (strtolower($case->getLabel()) === strtolower($this->query)) {
                return $case->value;
            }
        }

        // Otherwise pass through as-is (raw value search)
        return $this->query;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getLogs(): array
    {
        $all = $this->eventRepository->searchWithFilter(
            $this->filter,
            $this->sort,
            $this->order,
            $this->resolvedQuery(),
            $this->startDate ?: null,
            $this->endDate ?: null,
        );

        $offset = ($this->page - 1) * $this->count;
        return array_slice($all, $offset, $this->count);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getTotalLogs(): int
    {
        return count(
            $this->eventRepository->searchWithFilter(
                $this->filter,
                $this->sort,
                $this->order,
                $this->resolvedQuery(),
                $this->startDate ?: null,
                $this->endDate ?: null,
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
