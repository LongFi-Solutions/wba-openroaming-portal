<?php

namespace App\Service\UserDeletion;

use App\Entity\DeletedUserData;
use App\Entity\User;
use App\Entity\UserExternalAuth;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserVerificationStatus;
use App\Service\EmailGenerator;
use App\Service\EventActions;
use App\Service\PgpEncryptionService;
use App\Service\ProfileManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use libphonenumber\PhoneNumber;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserDeletionService
{
    public function __construct(
        private ProfileManager $profileManager,
        private EventActions $eventActions,
        private EntityManagerInterface $entityManager,
        private PgpEncryptionService $encryptionService,
        private TranslatorInterface $translator,
        private UserEventDataEncryptionService $userEventDataEncryptionService,
        private EmailGenerator $emailGenerator,
    ) {
    }

    /**
     * @param UserExternalAuth[] $userExternalAuths
     * @return array<string, mixed>
     * @throws \JsonException
     * @throws ORMException
     */
    public function deleteUser(User $user, array $userExternalAuths, Request $request, User $admin): array
    {
        // Capture IDs before any change
        $deletedUserById = $user->getId();
        $adminId = $admin->getId();

        // Notify the user before their data is wiped
        try {
            $this->emailGenerator->sendAccountDeletionEmail($user);
        } catch (TransportExceptionInterface) {
        }

        // Build the user data
        $phoneNumber = null;
        if ($user->getPhoneNumber() instanceof PhoneNumber) {
            $phoneNumber = "+" .
                $user->getPhoneNumber()->getCountryCode() .
                $user->getPhoneNumber()->getNationalNumber();
        }

        $deletedUserData = [
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail() ?? 'This value is empty',
            'phoneNumber' => $phoneNumber ?? 'This value is empty',
            'firstName' => $user->getFirstName() ?? 'This value is empty',
            'lastName' => $user->getLastName() ?? 'This value is empty',
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'bannedAt' => $user->getBannedAt()?->format('Y-m-d H:i:s'),
            'deletedAt' => new DateTime(),
        ];

        $deletedUserExternalAuthData = [];
        foreach ($userExternalAuths as $externalAuth) {
            $deletedUserExternalAuthData[] = [
                'provider' => $externalAuth->getProvider(),
                'providerId' => $externalAuth->getProviderId(),
            ];
        }

        $combinedData = [
            'user' => $deletedUserData,
            'externalAuths' => $deletedUserExternalAuthData,
        ];
        $jsonDataCombined = json_encode($combinedData, JSON_THROW_ON_ERROR);

        // Encrypt the user data
        $pgpEncryptedData = $this->encryptionService->encrypt($jsonDataCombined);

        if (!is_string($pgpEncryptedData) || $pgpEncryptedData === '' || $pgpEncryptedData === '0') {
            return [
                'success' => false,
                'message' => $this->translator->trans('encryptionFailed', [], 'UserDeletionService'),
            ];
        }

        // Check for special error signals returned by the service
        if ($pgpEncryptedData === UserVerificationStatus::MISSING_PUBLIC_KEY_CONTENT->value) {
            return [
                'success' => false,
                'message' => $this->translator->trans('publicKeyMissing', [], 'UserDeletionService'),
            ];
        }
        if ($pgpEncryptedData === UserVerificationStatus::EMPTY_PUBLIC_KEY_CONTENT->value) {
            return [
                'success' => false,
                'message' => $this->translator->trans('publicKeyEmpty', [], 'UserDeletionService'),
            ];
        }

        // Encrypt all existing events for this user BEFORE any entity change
        try {
            $this->userEventDataEncryptionService->encryptUserEventsMetadata($user);
        } catch (RuntimeException) {
            return [
                'success' => false,
                'message' => $this->translator->trans('encryptionEventFailed', [], 'UserDeletionService'),
            ];
        }

        // Prepare the user entity
        $deletedUserDataEntity = new DeletedUserData();
        $deletedUserDataEntity->setPgpEncryptedJsonFile($pgpEncryptedData);
        $deletedUserDataEntity->setUser($user);

        $user->setUuid((string)$user->getId());
        $user->setEmail(null);
        $user->setPhoneNumber(null);
        $user->setPassword((string)$user->getId());
        $user->setFirstName(null);
        $user->setLastName(null);
        $user->setDeletedAt(new DateTime());

        foreach ($userExternalAuths as $externalAuth) {
            $this->entityManager->remove($externalAuth);
        }

        $this->profileManager->disableProfiles(
            $user,
            UserRadiusProfileRevokeReason::USER_ACCOUNT_DELETED->value
        );

        // Persist changes
        $this->entityManager->persist($deletedUserDataEntity);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Build the deletion event metadata using pre-captured IDs
        $eventMetadata = [
            EventMetadataKeysType::IP->value => $request->getClientIp(),
            EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
            EventMetadataKeysType::UUID->value => (string)$adminId,
            EventMetadataKeysType::PERFORMED_ON_ID->value => $deletedUserById,
        ];

        // Encrypt the sensitive fields of the deletion event inline,
        $encryptedUuid = $this->encryptionService->encrypt((string)$adminId);
        if (is_string($encryptedUuid) && $encryptedUuid !== '') {
            $eventMetadata[EventMetadataKeysType::UUID->value] = $encryptedUuid;
        }

        // Save the deletion event — already encrypted
        $this->eventActions->saveEvent(
            $admin,
            AnalyticalEventType::DELETED_USER_BY->value,
            new DateTime(),
            $eventMetadata,
            true
        );

        return [
            'success' => true,
            'message' => $this->translator->trans('userSuccessfullyDeleted', [], 'UserDeletionService'),
        ];
    }
}
