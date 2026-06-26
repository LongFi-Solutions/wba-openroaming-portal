<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Service\EventActions;
use DateTime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

readonly class LogoutSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private EventActions $eventActions,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogoutSuccess',
        ];
    }

    public function onLogoutSuccess(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        $request = $event->getRequest();

        if ($user instanceof User) {
            $isDeleted = $user->getDeletedAt() !== null;

            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $isDeleted ? null : $user->getUuid(),
                EventMetadataKeysType::ID->value => $user->getId(),
            ];

            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::LOGOUT_REQUEST->value,
                new DateTime(),
                $eventMetadata,
                $isDeleted  // <-- pass containsEncryptedData = true when deleted
            );
        }
    }
}
