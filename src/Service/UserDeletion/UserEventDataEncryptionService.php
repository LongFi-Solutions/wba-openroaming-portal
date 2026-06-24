<?php

namespace App\Service\UserDeletion;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventMetadataKeysType;
use App\Repository\EventRepository;
use App\Service\PgpEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

readonly class UserEventDataEncryptionService
{
    /**
     * Metadata keys that contain direct RGPD-sensitive values (strings).
     */
    private const SENSITIVE_SCALAR_KEYS = [
        EventMetadataKeysType::UUID->value,
        EventMetadataKeysType::PERFORMED_ON_UUID->value,
    ];

    /**
     * Metadata keys whose value is a nested associative array of sensitive fields
     * (e.g. "New data" => ["First Name" => ..., "Last Name" => ...]).
     */
    private const SENSITIVE_NESTED_KEYS = [
        EventMetadataKeysType::USER_OLD_DATA->value,
        EventMetadataKeysType::USER_NEW_DATA->value,
    ];

    public function __construct(
        private EventRepository $eventRepository,
        private PgpEncryptionService $pgpEncryptionService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Finds all Event rows linked to the given user and encrypts any RGPD-sensitive
     * values inside their metadata. Should be called before the User entity is
     * anonymised so the ManyToOne join can still resolve.
     *
     * @throws RuntimeException When PGP encryption fails for any event.
     */
    public function encryptUserEventsMetadata(User $user): void
    {
        /** @var Event[] $events */
        $events = $this->eventRepository->findBy(['user' => $user]);

        foreach ($events as $event) {
            $metadata = $event->getEventMetadata();
            if ($metadata === null) {
                continue;
            }

            $metadata = $this->encryptScalarFields($metadata);
            $metadata = $this->encryptNestedFields($metadata);

            $event->setEventMetadata($metadata);
            $this->entityManager->persist($event);
        }

        $this->entityManager->flush();
    }

    /**
     * Encrypts top-level scalar sensitive fields in-place.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function encryptScalarFields(array $metadata): array
    {
        foreach (self::SENSITIVE_SCALAR_KEYS as $key) {
            if (!array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];

            // Only encrypt non-empty string values — nulls and already-processed
            // values are left as-is.
            if (!is_string($value) || $value === '') {
                continue;
            }

            $metadata[$key] = $this->encryptValue($value, $key);
        }

        return $metadata;
    }

    /**
     * Encrypts values inside known nested-array sensitive fields.
     * Each individual sub-value is encrypted separately so the key names
     * (e.g. "First Name") are preserved for audit readability.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function encryptNestedFields(array $metadata): array
    {
        foreach (self::SENSITIVE_NESTED_KEYS as $key) {
            if (!array_key_exists($key, $metadata)) {
                continue;
            }

            $nested = $metadata[$key];

            if (!is_array($nested)) {
                continue;
            }

            foreach ($nested as $subKey => $subValue) {
                if (!is_string($subValue) || $subValue === '') {
                    continue;
                }

                $nested[$subKey] = $this->encryptValue($subValue, $key . '.' . $subKey);
            }

            $metadata[$key] = $nested;
        }

        return $metadata;
    }

    /**
     * Encrypts a single string value via PGP, throwing a RuntimeException on any failure.
     *
     * @throws RuntimeException
     */
    private function encryptValue(string $value, string $fieldContext): string
    {
        $result = $this->pgpEncryptionService->encrypt($value);

        if (!is_string($result) || $result === '') {
            throw new RuntimeException(
                sprintf(
                    'PGP encryption failed for metadata field "%s". The encryption service returned an unexpected result.',
                    $fieldContext
                )
            );
        }

        return $result;
    }
}
