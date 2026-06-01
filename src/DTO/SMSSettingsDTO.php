<?php

namespace App\DTO;

use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;

class SMSSettingsDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 32, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $smsUsername = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 32, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $smsUserId = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 32, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $smsHandle = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 11, maxMessage: 'fieldCannotBeLongerThan')]
    public ?string $smsFrom = null;

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
     * @var string[]|null
     */
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?array $defaultRegionPhoneInputs = null;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->smsUsername = $data[SettingName::SMS_USERNAME->value]['value'] ?? null;
        $this->smsUserId = $data[SettingName::SMS_USER_ID->value]['value'] ?? null;
        $this->smsHandle = $data[SettingName::SMS_HANDLE->value]['value'] ?? null;
        $this->smsFrom = $data[SettingName::SMS_FROM->value]['value'] ?? null;
        $regionValue = $data[SettingName::DEFAULT_REGION_PHONE_INPUTS->value]['value'] ?? null;
        $this->defaultRegionPhoneInputs = $regionValue
            ? array_map(trim(...), explode(',', $regionValue))
            : [];
        $this->timeIntervalBetweenRequests =
            isset($data[SettingName::SMS_TIME_INTERVAL_BETWEEN_REQUESTS->value]['value'])
            ? (int)$data[SettingName::SMS_TIME_INTERVAL_BETWEEN_REQUESTS->value]['value']
            : null;
        $this->timeIntervalToResetAttempts =
            isset($data[SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value]['value'])
            ? (int)$data[SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value]['value']
            : null;
        $this->attemptsNumber = isset($data[SettingName::SMS_ATTEMPTS_NUMBER->value]['value'])
            ? (int)$data[SettingName::SMS_ATTEMPTS_NUMBER->value]['value']
            : null;
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::SMS_USERNAME->value => ['value' => $this->smsUsername],
            SettingName::SMS_USER_ID->value => ['value' => $this->smsUserId],
            SettingName::SMS_HANDLE->value => ['value' => $this->smsHandle],
            SettingName::SMS_FROM->value => ['value' => $this->smsFrom],
            SettingName::DEFAULT_REGION_PHONE_INPUTS->value => [
                'value' => $this->defaultRegionPhoneInputs
                    ? implode(', ', $this->defaultRegionPhoneInputs)
                    : null,
            ],
            SettingName::SMS_TIME_INTERVAL_BETWEEN_REQUESTS->value => ['value' => $this->timeIntervalBetweenRequests],
            SettingName::SMS_TIME_INTERVAL_TO_RESET_ATTEMPTS->value => ['value' => $this->timeIntervalToResetAttempts],
            SettingName::SMS_ATTEMPTS_NUMBER->value => ['value' => $this->attemptsNumber],
        ];
    }
}
