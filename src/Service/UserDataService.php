<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserRadiusProfile;
use App\Enum\AdminRoleType;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\Repository\EventRepository;

readonly class UserDataService
{
    public function __construct(
        private EventRepository $eventRepository,
        private RadiusAccountingRepository $radiusAccountingRepository,
        private RadiusAuthsRepository $radiusAuthsRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getData(User $user): array
    {
        // Get all radius usernames from the user's profiles
        $radiusUsernames = array_map(
            static fn(UserRadiusProfile $profile) => $profile->getRadiusUser(),
            $user->getUserRadiusProfiles()->toArray()
        );

        // Fetch last accounting record across all radius profiles
        $lastAccounting = null;
        if (!empty($radiusUsernames)) {
            $lastAccounting = $this->radiusAccountingRepository->createQueryBuilder('ra')
                ->where('ra.username IN (:usernames)')
                ->setParameter('usernames', $radiusUsernames)
                ->orderBy('ra.acctStartTime', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        // Fetch last auth record across all radius profiles
        $lastAuth = null;
        if (!empty($radiusUsernames)) {
            $lastAuth = $this->radiusAuthsRepository->createQueryBuilder('ra')
                ->where('ra.username IN (:usernames)')
                ->setParameter('usernames', $radiusUsernames)
                ->orderBy('ra.authdate', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        // Fetch all accounting sessions across all profiles
        $allSessions = [];
        if (!empty($radiusUsernames)) {
            $allSessions = $this->radiusAccountingRepository->createQueryBuilder('ra')
                ->where('ra.username IN (:usernames)')
                ->setParameter('usernames', $radiusUsernames)
                ->orderBy('ra.acctStartTime', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        }

        return [
            // Core user
            'user' => $user,

            // Auth providers (Portal/Google/SAML/etc)
            'externalAuths' => $user->getUserExternalAuths()->toArray(),

            // Radius profiles with their status
            'radiusProfiles' => $user->getUserRadiusProfiles()->toArray(),

            // OTP codes
            'otpCodes' => $user->getOTPcodes()->toArray(),

            // Recent activity log events
            'recentEvents' => $this->eventRepository->searchWithFilter(
                sort: 'event_datetime',
                order: 'desc',
                user: $user,
            ),

            // Radius connection data
            'lastAccounting' => $lastAccounting,
            'lastAuth' => $lastAuth,
            'recentSessions' => $allSessions,

            // Computed helpers for the template
            'isAdmin' => in_array(AdminRoleType::ROLE_ADMIN->value, $user->getRoles(), true) ||
                in_array(AdminRoleType::ROLE_SUPER_ADMIN->value, $user->getRoles(), true),
            'isBanned' => $user->getBannedAt() !== null,
            'isDeleted' => $user->getDeletedAt() !== null,
            'primaryAuth' => $user->getUserExternalAuths()->first() ?: null,
        ];
    }
}