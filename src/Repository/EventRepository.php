<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Event[]    findAll()
 * phpcs:ignore Generic.Files.LineLength.TooLong
 * @method Event[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, ?int $limit = null, ?int $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function save(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Event $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Event[] Returns an array of Event objects
     * @throws \DateMalformedStringException
     */
    public function searchWithFilter(
        string $filter = 'all',
        string $sort = 'event_datetime',
        string $order = 'desc',
        ?string $searchTerm = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?User $user = null,  // ← add this
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->join('e.user', 'u');

        // --- Filter by user ---
        if ($user !== null) {
            $qb->andWhere('e.user = :user')
                ->setParameter('user', $user);
        }

        // --- Search by user email/uuid/EventName ---
        if ($searchTerm) {
            $qb->andWhere(
                'u.email LIKE :search OR u.uuid LIKE :search OR e.event_name LIKE :search'
            )
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        // Date range filter
        if ($startDate) {
            try {
                $start = new DateTime($startDate);
                $qb->andWhere('e.event_datetime >= :startDate')
                    ->setParameter('startDate', $start);
            } catch (Exception) {
                // invalid date, skip
            }
        }
        if ($endDate) {
            try {
                $end = new DateTime($endDate);
                $qb->andWhere('e.event_datetime <= :endDate')
                    ->setParameter('endDate', $end);
            } catch (Exception) {
                // invalid date, skip
            }
        }

        // --- Filter by event type group ---
        match ($filter) {
            'user_actions' => $qb->andWhere('e.event_name IN (:events)')
                ->setParameter('events', [
                    AnalyticalEventType::USER_CREATION->value,
                    AnalyticalEventType::USER_VERIFICATION->value,
                    AnalyticalEventType::USER_ACCOUNT_DELETION->value,
                    AnalyticalEventType::USER_ACCOUNT_UPDATE->value,
                ]),
            'admin_actions' => $qb->andWhere('e.event_name IN (:events)')
                ->setParameter('events', [
                    AnalyticalEventType::ADMIN_CREATION->value,
                    AnalyticalEventType::ADMIN_ADDED_PERMISSIONS->value,
                    AnalyticalEventType::ADMIN_REMOVED_PERMISSIONS->value,
                    AnalyticalEventType::ADMIN_ADDED_NEW_USER->value,
                ]),
            'auth_events' => $qb->andWhere('e.event_name IN (:events)')
                ->setParameter('events', [
                    AnalyticalEventType::LOGIN_TRADITIONAL_REQUEST->value,
                    AnalyticalEventType::GOOGLE_LOGIN_REQUEST->value,
                    AnalyticalEventType::MICROSOFT_LOGIN_REQUEST->value,
                    AnalyticalEventType::LOGOUT_REQUEST->value,
                ]),
            'settings_changes' => $qb->andWhere('e.event_name LIKE :prefix')
                ->setParameter('prefix', 'SETTING_%'),
            'certificate_events' => $qb->andWhere('e.event_name LIKE :prefix')
                ->setParameter('prefix', 'CERTIFICATE_%'),
            default => null, // 'all' — no filter applied
        };

        // --- Sorting ---
        $allowedSorts = ['event_datetime', 'event_name'];
        $allowedOrders = ['asc', 'desc'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'event_datetime';
        $order = in_array(strtolower($order), $allowedOrders, true) ? $order : 'desc';
        $qb->orderBy('e.' . $sort, $order);

        return $qb->getQuery()->getResult();
    }

    public function countByEventGroup(): array
    {
        return [
            'all' => $this->count([]),
            'user_actions' => $this->countByPrefix('USER_'),
            'admin_actions' => $this->countByPrefix('ADMIN_'),
            'auth_events' => $this->countByPrefix('LOGIN_', 'LOGOUT_'),
            'settings_changes' => $this->countByPrefix('SETTING_'),
            'certificate_events' => $this->countByPrefix('CERTIFICATE_'),
        ];
    }

    /**
     * Helper to count by prefix
     */
    private function countByPrefix(string ...$prefixes): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)');

        $orX = $qb->expr()->orX();
        foreach ($prefixes as $i => $prefix) {
            $orX->add("e.event_name LIKE :prefix$i");
            $qb->setParameter("prefix$i", $prefix . '%');
        }

        return (int)$qb->andWhere($orX)->getQuery()->getSingleScalarResult();
    }

    /**
     * Find the latest 'USER_SMS_ATTEMPT' event for the given user.
     *
     * @throws NonUniqueResultException
     */
    public function findLatestSmsAttemptEvent(User $user): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', AnalyticalEventType::USER_SMS_ATTEMPT->value)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the latest 'TWO_FA_CODE_SENDED' event for the given user.
     *
     * @throws NonUniqueResultException
     */
    public function findLatest2FACodeAttemptEvent(User $user, string $eventType): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', $eventType)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the $maxResults number of 'TWO_FA_CODE_SENDED' events for the given user.
     * @return Event[] Returns an array of Event objects
     */
    public function find2FACodeAttemptEvent(User $user, int $maxResults, DateTime $time, string $eventType): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->andWhere('e.event_datetime >= :datetime')
            ->setParameter('user', $user)
            ->setParameter('event_name', $eventType)
            ->setParameter('datetime', $time)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();
    }

    public function findLastLinkSent(User $user, \DateTime $time): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name IN (:event_names)')
            ->andWhere('e.event_datetime >= :datetime')
            ->setParameter('user', $user)
            ->setParameter('event_names', [
                AnalyticalEventType::LOGIN_WITH_UUID_ONLY_LINK->value,
                AnalyticalEventType::LOGIN_WITH_UUID_ONLY_CODE->value,
            ])
            ->setParameter('datetime', $time)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the latest '$eventLog' from AnalyticalEventType Enum for the given user.
     *
     * @param $eventLog // from ENUM AnalyticalEventType
     * @throws NonUniqueResultException
     */
    public function findLatestRequestAttemptEvent(User $user, string $eventLog): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.event_name = :event_name')
            ->setParameter('user', $user)
            ->setParameter('event_name', $eventLog)
            ->orderBy('e.event_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find events where any field is null or empty.
     *
     * @return Event[] Returns an array of Event objects
     */
    public function findEventsWithNullOrEmptyFields(): array
    {
        return $this->createQueryBuilder('e')
            ->where(
                $this->createQueryBuilder('e')
                    ->expr()->orX(
                        'e.event_name IS NULL',
                        'e.event_name = :emptyString',
                        'e.event_metadata IS NULL',
                        'e.event_metadata = :emptyString',
                        'e.user IS NULL'
                    )
            )
            ->setParameter('emptyString', '')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find DOWNLOAD_PROFILE related events.
     *
     * @return Event[] Returns an array of Event objects
     * @throws \JsonException
     */
    public function findDownloadProfileEvents(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.user', 'u')
            ->andWhere('e.event_name = :event')
            ->andWhere('e.event_datetime BETWEEN :start AND :end')
            ->setParameter('event', AnalyticalEventType::DOWNLOAD_PROFILE->value)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts USER_CREATION events by platform mode.
     *
     * @return Event[] Returns an array of Event objects
     */
    public function findUserCreationEvents(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.user', 'u')
            ->andWhere('e.event_name = :event')
            ->andWhere('e.event_datetime BETWEEN :start AND :end')
            ->setParameter('event', AnalyticalEventType::USER_CREATION->value)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }
}
