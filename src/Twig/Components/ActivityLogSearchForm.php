<?php

namespace App\Twig\Components;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Repository\EventRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

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
    public int $count = 7;

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

    /**
     * @return Paginator<Event>
     */
    #[ExposeInTemplate]
    public function getEvents(): Paginator
    {
        return new Paginator($this->getQueryBuilder());
    }

    /**
     * @return array<string, int>
     */
    #[ExposeInTemplate]
    public function getEventCounts(): array
    {
        return $this->eventRepository->countByEventGroup($this->user);
    }

    #[ExposeInTemplate]
    public function getTotalPages(): int
    {
        return (int)ceil(count($this->getEvents()) / $this->count);
    }

    #[ExposeInTemplate]
    public function isUserScoped(): bool
    {
        return $this->user instanceof User;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[ExposeInTemplate]
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

    #[LiveAction]
    public function changeFilter(#[LiveArg] string $filter): void
    {
        $this->filter = $filter;
        $this->page = 1;
    }

    #[LiveAction]
    public function prevPage(): void
    {
        $this->page--;
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->page++;
    }

    #[LiveAction]
    public function changeSort(#[LiveArg] string $field): void
    {
        $this->sort = $field;
        $this->order = $this->order === 'desc' ? 'asc' : 'desc';
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

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->eventRepository->searchWithFilter(
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
}
