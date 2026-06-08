<?php

namespace App\Twig\Components;

use App\Entity\User;
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

    #[LiveProp]
    public ?User $user = null;

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

    private ?int $cachedTotal = null;
    private ?array $cachedLogs = null;

    private ?array $cachedEventCounts = null;

    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
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
        if ($this->query === '' || $this->query === '0') {
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
     * @return array<int, \App\Entity\Event>
     * @throws \DateMalformedStringException
     */
    public function getLogs(): array
    {
        if ($this->cachedLogs === null) {
            $this->cachedLogs = $this->eventRepository->searchWithFilter(
                $this->filter,
                $this->sort,
                $this->order,
                $this->resolvedQuery(),
                $this->startDate ?: null,
                $this->endDate ?: null,
                $this->user,
                $this->page,
                $this->count
            );
        }
        return $this->cachedLogs;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getTotalLogs(): int
    {
        if ($this->cachedTotal === null) {
            $this->cachedTotal = $this->eventRepository->countWithFilter(
                $this->filter,
                $this->resolvedQuery(),
                $this->startDate ?: null,
                $this->endDate ?: null,
                $this->user
            );
        }
        return $this->cachedTotal;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getTotalPages(): int
    {
        return (int)ceil($this->getTotalLogs() / $this->count);
    }

    /**
     * @return array<string, int>
     */
    public function getEventCounts(): array
    {
        if ($this->cachedEventCounts === null) {
            // When scoped to a user, count only their events
            $this->cachedEventCounts = $this->user instanceof User
                ? $this->eventRepository->countByEventGroup($this->user)
                : $this->eventRepository->countByEventGroup();
        }
        return $this->cachedEventCounts;
    }

    public function isUserScoped(): bool
    {
        return $this->user instanceof User;
    }
}
