<?php

namespace App\Service\ActivityLog;

use App\Entity\Event;
use App\Enum\ExportFileType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ActivityLogExporter
{
    public function __construct(
        private EventRepository $eventRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function export(ExportFilters $filters, string $format): StreamedResponse
    {
        $user = $filters->userId
            ? $this->userRepository->find($filters->userId)
            : null;

        $events = $this->eventRepository
            ->searchWithFilterUnpaginated(
                $filters->filter,
                $filters->sort,
                $filters->order,
                $filters->query,
                $filters->startDate,
                $filters->endDate,
                $user,
            )
            ->getQuery()
            ->toIterable();

        $date = new DateTimeImmutable()->format('Y-m-d');

        return match ($format) {
            ExportFileType::CSV->value => $this->streamCsv($events, $date),
            ExportFileType::JSON->value => $this->streamJson($events, $date),
            default => throw new RuntimeException(sprintf('Unsupported export format "%s".', $format)),
        };
    }

    /**
     * @param iterable<Event> $events
     */
    private function streamCsv(iterable $events, string $date): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($events): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Unable to open output stream.');
            }

            fputcsv($handle, ['UUID', 'Action', 'IP Address', 'Created At', 'Metadata'], escape: '\\');

            foreach ($events as $event) {
                fputcsv(
                    $handle,
                    [
                        $event->getUser()?->getUuid(),
                        $event->getEventName(),
                        $event->getEventMetadata()['ip'] ?? null,
                        $event->getEventDatetime()?->format('Y-m-d H:i:s'),
                        json_encode($event->getEventMetadata(), JSON_THROW_ON_ERROR),
                    ],
                    escape: '\\'
                );
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "attachment; filename=\"activity-log-{$date}.csv\"");

        return $response;
    }

    /**
     * @param iterable<Event> $events
     */
    private function streamJson(iterable $events, string $date): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($events): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Unable to open output stream.');
            }

            fwrite($handle, '[');
            $first = true;

            foreach ($events as $event) {
                if (!$first) {
                    fwrite($handle, ',');
                }
                fwrite($handle, json_encode([
                    'uuid' => $event->getUser()?->getUuid(),
                    'action' => $event->getEventName(),
                    'ip_address' => $event->getEventMetadata()['ip'] ?? null,
                    'created_at' => $event->getEventDatetime()?->format('Y-m-d H:i:s'),
                    'metadata' => $event->getEventMetadata(),
                ], JSON_THROW_ON_ERROR));
                $first = false;
            }

            fwrite($handle, ']');
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', "attachment; filename=\"activity-log-{$date}.json\"");

        return $response;
    }
}
