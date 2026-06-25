<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventMetadataKeysType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

readonly class EventActions
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $eventMetadata
     */
    public function saveEvent(
        User $user,
        string $eventName,
        DateTime $dateTime,
        array $eventMetadata,
        bool $containsEncryptedData = false,
    ): void {
        $event = new Event();
        $event->setUser($user);
        $event->setEventDatetime($dateTime);
        $event->setEventName($eventName);
        $metadata = [
            EventMetadataKeysType::IP->value => $eventMetadata['ip'] ?? null,
            EventMetadataKeysType::USER_AGENT->value => $eventMetadata['user_agent'] ?? null,
            EventMetadataKeysType::UUID->value => $eventMetadata['uuid'] ?? null,
        ];

        foreach ($eventMetadata as $key => $value) {
            if (!array_key_exists($key, $metadata)) {
                $metadata[$key] = $value;
            }
        }

        $event->setEventMetadata($metadata);
        $event->setContainsEncryptedData($containsEncryptedData);
        
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }
}
