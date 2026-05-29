<?php

namespace App\Service;

use App\Entity\User;
use App\RadiusDb\Repository\RadiusAccountingRepository;
use App\RadiusDb\Repository\RadiusAuthsRepository;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\EventRepository;

readonly class UserDataService
{
    public function __construct(
        private EventRepository $eventRepository,
        private RadiusAccountingRepository $radiusAccountingRepository,
        private RadiusAuthsRepository $radiusAuthsRepository,
        private RadiusUserRepository $radiusUserRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getUserData(User $user): array
    {
        return [
            'user' => $user,
            'externalAuths' => $user->getUserExternalAuths()->toArray(),
            'radiusProfiles' => $user->getUserRadiusProfiles()->toArray(),
            'otpCodes' => $user->getOtpCodes()->toArray(),
            'recentEvents' => $this->eventRepository->searchWithFilter(
                sort: 'event_datetime',
                order: 'desc',
                user: $user,
            ),
            'radiusUser' => $this->radiusUserRepository->findOneBy(['username' => $user->getUuid()]),
            'lastAccounting' => $this->radiusAccountingRepository->findOneBy(
                ['username' => $user->getUuid()],
                ['acctstarttime' => 'DESC']
            ),
            'lastAuth' => $this->radiusAuthsRepository->findOneBy(
                ['username' => $user->getUuid()],
                ['authdate' => 'DESC']
            ),
        ];
    }

    public function getAdminData(User $user): array
    {
        return [
            'user' => $user,
            'potato' => 'TODO MAKE THIS FUNCTION LATER' // TODO MAKE THIS FUNCTION LATER
        ];
    }
}