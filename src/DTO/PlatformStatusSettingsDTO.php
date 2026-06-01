<?php

namespace App\DTO;

use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\OperationMode;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;

class PlatformStatusSettingsDTO
{
    #[Assert\NotBlank(message: 'selectOption')]
    #[Assert\Expression(
        "this.platformMode != '" .
        PlatformMode::LIVE->value .
        "' or this.userVerification == '" .
        OperationMode::ON->value . "'",
        message: "enforceUserVerificationEnabled"
    )]
    public ?string $userVerification = null;

    #[Assert\NotBlank(message: 'selectOption')]
    public ?string $platformMode = null;

    #[Assert\NotBlank(message: 'selectOption')]
    public ?string $turnstileChecker = null;

    #[Assert\NotBlank(message: 'selectOption')]
    public ?string $apiStatus = null;

    #[Assert\NotBlank(message: 'timerValueRequired')]
    #[Length(max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[GreaterThanOrEqual(value: 0, message: 'timerShouldNotBeLessThan')]
    public ?int $userDeleteTime = null;

    #[Assert\NotBlank(message: 'pleaseSetTimer')]
    #[Length(max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[GreaterThanOrEqual(value: 1, message: 'timerShouldNotBeLessThanProfileNotification')]
    public ?int $timeIntervalNotification = null;

    #[Assert\NotBlank(message: 'timerValueRequired')]
    #[Length(max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[GreaterThanOrEqual(value: 0, message: 'timerShouldNotBeLessThan')]
    public ?int $timeIntervalBetweenRequests = null;

    #[Assert\NotBlank(message: 'timerValueRequired')]
    #[Length(max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[GreaterThanOrEqual(value: 0, message: 'timerShouldNotBeLessThan')]
    public ?int $timeIntervalToResetAttempts = null;

    #[Assert\NotBlank(message: 'timerValueRequired')]
    #[Length(max: 3, maxMessage: 'fieldCannotBeLongerThan')]
    #[GreaterThanOrEqual(value: 0, message: 'timerShouldNotBeLessThan')]
    public ?int $attemptsNumber = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->userVerification = $data[SettingName::USER_VERIFICATION->value]['value'] ?? null;
        $this->platformMode = $data[SettingName::PLATFORM_MODE->value]['value'] ?? null;
        $this->turnstileChecker = $data[SettingName::TURNSTILE_CHECKER->value]['value'] ?? null;
        $this->apiStatus = $data[SettingName::API_STATUS->value]['value'] ?? null;
        $this->userDeleteTime = isset($data[SettingName::USER_DELETE_TIME->value]['value'])
            ? (int)$data[SettingName::USER_DELETE_TIME->value]['value']
            : null;
        $this->timeIntervalNotification = isset($data[SettingName::TIME_INTERVAL_NOTIFICATION->value]['value'])
            ? (int)$data[SettingName::TIME_INTERVAL_NOTIFICATION->value]['value']
            : null;
        $this->timeIntervalBetweenRequests =
            isset($data[SettingName::EMAIL_TIME_INTERVAL_BETWEEN_REQUESTS->value]['value'])
            ? (int)$data[SettingName::EMAIL_TIME_INTERVAL_BETWEEN_REQUESTS->value]['value']
            : null;
        $this->timeIntervalToResetAttempts =
            isset($data[SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value]['value'])
            ? (int)$data[SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value]['value']
            : null;
        $this->attemptsNumber = isset($data[SettingName::EMAIL_ATTEMPTS_NUMBER->value]['value'])
            ? (int)$data[SettingName::EMAIL_ATTEMPTS_NUMBER->value]['value']
            : null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|int|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::USER_VERIFICATION->value => ['value' => $this->userVerification],
            SettingName::PLATFORM_MODE->value => ['value' => $this->platformMode],
            SettingName::TURNSTILE_CHECKER->value => ['value' => $this->turnstileChecker],
            SettingName::API_STATUS->value => ['value' => $this->apiStatus],
            SettingName::USER_DELETE_TIME->value => ['value' =>
                $this->userDeleteTime],
            SettingName::TIME_INTERVAL_NOTIFICATION->value => ['value' => $this->timeIntervalNotification],
            SettingName::EMAIL_TIME_INTERVAL_BETWEEN_REQUESTS->value => ['value' => $this->timeIntervalBetweenRequests],
            SettingName::EMAIL_TIME_INTERVAL_TO_RESET_ATTEMPTS->value =>
                ['value' => $this->timeIntervalToResetAttempts],
            SettingName::EMAIL_ATTEMPTS_NUMBER->value => ['value' => $this->attemptsNumber],
        ];
    }
}
