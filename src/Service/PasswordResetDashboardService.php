<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\SettingName;
use App\Enum\UserProvider;
use App\Repository\EventRepository;
use App\Repository\UserExternalAuthRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class PasswordResetDashboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailGenerator $emailGenerator,
        private SendSMS $sendSMS,
        private EventActions $eventActions,
        private EventRepository $eventRepository,
        private UserExternalAuthRepository $userExternalAuthRepository,
        private GetSettings $getSettings,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Resets the user's password, sends notifications, and logs the event.
     * Returns an array of flash messages: [['type' => 'success'|'error', 'message' => '...']]
     *
     * @return array<int, array{type: string, message: string}>
     * * @throws \DateMalformedStringException
     * * @throws \DateMalformedIntervalStringException
     * * @throws TransportExceptionInterface
     */
    public function resetPassword(
        User $user,
        string $newPassword,
        string $clientIp,
        ?string $userAgent,
        User $byUser
    ): array {
        $flashes = [];

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setForgotPasswordRequest(true);
        $this->entityManager->flush();

        if ($user->getEmail()) {
            $this->emailGenerator->sendResetPasswordEmailByAdmin($user, $newPassword);
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI->value,
                new DateTime(),
                [
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                    'edited' => $user->getUuid(),
                    'by' => $byUser->getUuid(),
                ]
            );
        }

        $userExternalAuth = $this->userExternalAuthRepository->findOneBy(['user' => $user]);

        if ($user->getPhoneNumber() && $userExternalAuth?->getProviderId() === UserProvider::PHONE_NUMBER->value) {
            $flashes = array_merge($flashes, $this->handleSmsReset($user, $newPassword, $clientIp, $byUser));
        }

        return $flashes;
    }

    /**
     * @return array<int, array{type: string, message: string}>
     * @throws \DateMalformedStringException
     * @throws \DateMalformedIntervalStringException
     */
    private function handleSmsReset(User $user, string $newPassword, string $clientIp, User $byUser): array
    {
        $flashes = [];
        $data = $this->getSettings->getSettings();

        $latestEvent = $this->eventRepository->findLatestRequestAttemptEvent(
            $user,
            AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI->value
        );

        $smsResendInterval = (is_array($data) && isset($data[SettingName::SMS_TIMER_RESEND->value]['value']))
            ? $data[SettingName::SMS_TIMER_RESEND->value]['value']
            : 5;

        $minInterval = new DateInterval('PT' . $smsResendInterval . 'M');
        $currentTime = new DateTime();

        $latestEventMetadata = $latestEvent instanceof Event ? $latestEvent->getEventMetadata() : [];
        $lastResetTime = isset($latestEventMetadata['lastResetAccountPasswordTime'])
            ? new DateTime($latestEventMetadata['lastResetAccountPasswordTime'])
            : null;
        $resetAttempts = $latestEventMetadata['resetAttempts'] ?? 0;

        $canSend = (!$latestEvent || $resetAttempts < 3)
            && (!$latestEvent || ($lastResetTime instanceof DateTime && $lastResetTime->add(
                $minInterval
            ) < $currentTime));

        if (!$canSend) {
            return $flashes;
        }

        $message = $this->translator->trans('newPasswordMessage', ['%password%' => $newPassword], 'controllers');
        $smsResponse = $this->sendSMS->sendSmsNoValidation($user, $message);

        if ($smsResponse !== '' && $smsResponse !== '0') {
            $flashes[] = [
                'type' => 'success',
                'message' => $this->translator->trans('passwordSentSMS', [], 'controllers')
            ];
            $this->eventActions->saveEvent(
                $user,
                AnalyticalEventType::USER_ACCOUNT_UPDATE_PASSWORD_FROM_UI->value,
                new DateTime(),
                [
                    'ip' => $clientIp,
                    'edited' => $user->getUuid(),
                    'by' => $byUser->getUuid(),
                    'resetAttempts' => $resetAttempts + 1,
                    'lastResetAccountPasswordTime' => $currentTime->format('Y-m-d H:i:s'),
                ]
            );
        } else {
            $flashes[] = [
                'type' => 'error',
                'message' => $this->translator->trans('passwordNotSentSMS', [], 'controllers')
            ];
        }

        return $flashes;
    }
}
