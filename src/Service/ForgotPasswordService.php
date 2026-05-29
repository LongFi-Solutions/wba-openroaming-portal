<?php

namespace App\Service;

use App\Entity\Event;
use App\Enum\AnalyticalEventType;
use App\Enum\ForgotPasswordEnum;
use App\Enum\SettingName;
use App\Repository\EventRepository;
use DateMalformedStringException;
use DateTime;

use function Symfony\Component\Clock\now;

readonly class ForgotPasswordService
{
    public function __construct(
        private EventRepository $eventRepository,
        private GetSettings $getSettings,
    )
    {
    }

    /**
     * @throws DateMalformedStringException
     */
    public Function userCanResetPassword($user): array
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();
        $event = $this->eventRepository->findLatestRequestAttemptEvent($user, AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value);
        if ($event instanceof Event) {
            $limitTimeToReset = new DateTime();
            $timeToResetAttempts = $data[SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value]['value'];
            $limitTimeToReset->modify('-' . $timeToResetAttempts . ' minutes');
            $events = $this->eventRepository->findLastEvents($user, AnalyticalEventType::FORGOT_PASSWORD_EMAIL_REQUEST->value, $limitTimeToReset);
            $attemptsNumber = (int)$data[SettingName::EMAIL_ATTEMPTS_NUMBER->value]['value'];
            if (count($events) < $attemptsNumber) {
                $lastEventTime = $event->getEventDatetime();
                $limitTime = new DateTime();
                $timeBetweenRequests = $data[SettingName::EMAIL_TIME_INTERVAL_BETWEEN_REQUESTS->value]['value'];
                $limitTime->modify('-'.$timeBetweenRequests.' seconds');
                if ($limitTime > $lastEventTime) {
                    return [
                        ForgotPasswordEnum::SUCCESS->value => true,
                        ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::SUCCESS->value,
                        ForgotPasswordEnum::TIME_LEFT->value => 0
                    ];
                }
                $timeLeft = $limitTime->diff($lastEventTime ?? now());
                return [
                    ForgotPasswordEnum::SUCCESS->value => false,
                    ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::TIME_BETWEEN_REQUESTS->value,
                    ForgotPasswordEnum::TIME_LEFT->value => $timeLeft
                ];
            }
            $firstEvent = $events[$attemptsNumber-1];
            $firstEventTime = $firstEvent->getEventDatetime();
            $timeLeft = $limitTimeToReset->diff($firstEventTime);
            return [
                ForgotPasswordEnum::SUCCESS->value => false,
                ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::ATTEMPTS_EXCEEDED->value,
                ForgotPasswordEnum::TIME_LEFT->value => $timeLeft
            ];
        }
        return [
            ForgotPasswordEnum::SUCCESS->value => true,
            ForgotPasswordEnum::MESSAGE_TYPE->value => ForgotPasswordEnum::SUCCESS->value,
            ForgotPasswordEnum::TIME_LEFT->value => 0
        ];
    }
}