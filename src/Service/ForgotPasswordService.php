<?php

namespace App\Service;

use App\DTO\ForgotPasswordDTO;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\ForgotPasswordEnum;
use App\Enum\SettingName;
use App\Repository\EventRepository;
use DateInterval;
use DateMalformedStringException;
use DateTime;

use function Symfony\Component\Clock\now;

readonly class ForgotPasswordService
{
    public function __construct(
        private EventRepository $eventRepository,
        private GetSettings $getSettings,
    ) {
    }

    /**
     * @return array{
     *      success: bool,
     *      messageType: string,
     *      timeLeft: mixed
     *  }
     * @throws DateMalformedStringException
     */
    public function userCanResetPassword(User $user, bool $isSMS = false): array
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();
        if ($isSMS) {
            $eventType = AnalyticalEventType::FORGOT_PASSWORD_SMS_REQUEST->value;
            $timeToResetAttemptsType = SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value;
            $timeBetweenRequestsType = SettingName::SMS_TIME_INTERVAL_BETWEEN_REQUESTS->value;
            $attemptsNumberType = SettingName::SMS_ATTEMPTS_NUMBER->value;
        } else {
            $eventType = AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value;
            $timeToResetAttemptsType = SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value;
            $timeBetweenRequestsType = SettingName::EMAIL_TIME_INTERVAL_BETWEEN_REQUESTS->value;
            $attemptsNumberType = SettingName::EMAIL_ATTEMPTS_NUMBER->value;
        }
        $event = $this->eventRepository->findLatestRequestAttemptEvent($user, $eventType);
        if ($event instanceof Event) {
            $limitTimeToReset = new DateTime();
            $timeToResetAttempts = $data[$timeToResetAttemptsType]['value'];
            $limitTimeToReset->modify('-' . $timeToResetAttempts . ' minutes');
            $events = $this->eventRepository->findLastEvents($user, $eventType, $limitTimeToReset);
            $attemptsNumber = (int)$data[$attemptsNumberType]['value'];
            if (count($events) < $attemptsNumber) {
                $lastEventTime = $event->getEventDatetime();
                $limitTime = new DateTime();
                $timeBetweenRequests = $data[$timeBetweenRequestsType]['value'];
                $limitTime->modify('-' . $timeBetweenRequests . ' seconds');
                if ($limitTime > $lastEventTime) {
                    return [
                        ForgotPasswordEnum::SUCCESS->value => true,
                        ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::SUCCESS->value,
                        ForgotPasswordEnum::TIME_LEFT->value => 0,
                    ];
                }
                $timeLeft = $limitTime->diff($lastEventTime);
                return [
                    ForgotPasswordEnum::SUCCESS->value => false,
                    ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::TIME_BETWEEN_REQUESTS->value,
                    ForgotPasswordEnum::TIME_LEFT->value => $timeLeft,
                ];
            }
            $firstEvent = $events[$attemptsNumber - 1];
            $firstEventTime = $firstEvent->getEventDatetime();
            $timeLeft = $limitTimeToReset->diff($firstEventTime ?? now());
            return [
                ForgotPasswordEnum::SUCCESS->value => false,
                ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::ATTEMPTS_EXCEEDED->value,
                ForgotPasswordEnum::TIME_LEFT->value => $timeLeft,
            ];
        }
        return [
            ForgotPasswordEnum::SUCCESS->value => true,
            ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::SUCCESS->value,
            ForgotPasswordEnum::TIME_LEFT->value => 0,
        ];
    }
}
