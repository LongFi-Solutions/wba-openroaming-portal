<?php

namespace App\Twig\Components;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
class UserSearchForm
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $filter = 'all';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $count = 7;

    #[LiveProp(writable: true)]
    public string $sort = 'createdAt';

    #[LiveProp(writable: true)]
    public string $order = 'desc';

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return User[]
     */
    #[ExposeInTemplate]
    public function getUsers(): array
    {
        $all = $this->userRepository->searchWithFilter(
            $this->filter,
            $this->sort,
            $this->order,
            $this->query ?: null,
        );

        return array_slice($all, ($this->page - 1) * $this->count, $this->count);
    }

    #[ExposeInTemplate]
    public function getTotalPages(): int
    {
        $all = $this->userRepository->searchWithFilter(
            $this->filter,
            $this->sort,
            $this->order,
            $this->query ?: null,
        );

        return (int)ceil(count($all) / $this->count);
    }

    /**
     * @return array<string, int>
     */
    #[ExposeInTemplate]
    public function getUserCounts(): array
    {
        return [
            'all' => $this->userRepository->countUsers(null, 'all'),
            'verified' => $this->userRepository->countVerifiedUsers(),
            'banned' => $this->userRepository->countBannedUsers(),
        ];
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
        if ($this->sort === $field) {
            $this->order = $this->order === 'desc' ? 'asc' : 'desc';
        } else {
            $this->sort = $field;
            $this->order = 'desc';
        }
    }
}
