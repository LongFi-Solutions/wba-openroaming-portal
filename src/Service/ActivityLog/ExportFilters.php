<?php

namespace App\Service\ActivityLog;

use Symfony\Component\HttpFoundation\Request;

final readonly class ExportFilters
{
    public function __construct(
        public string $filter = 'all',
        public string $sort = 'event_datetime',
        public string $order = 'desc',
        public ?string $query = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?int $userId = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            filter: $request->query->getString('filter', 'all'),
            sort: $request->query->getString('sort', 'event_datetime'),
            order: $request->query->getString('order', 'desc'),
            query: $request->query->getString('query') ?: null,
            startDate: $request->query->getString('startDate') ?: null,
            endDate: $request->query->getString('endDate') ?: null,
            userId: $request->query->getInt('userId') ?: null,
        );
    }
}
