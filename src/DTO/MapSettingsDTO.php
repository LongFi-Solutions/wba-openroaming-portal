<?php

namespace App\DTO;

use App\Enum\OperationMode;
use App\Enum\SettingName;
use Symfony\Component\Validator\Constraints as Assert;

class MapSettingsDTO
{
    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Regex(pattern: '/^-?\d+(\.\d+)?$/', message: 'decimalNumber')]
    #[Assert\Range(notInRangeMessage: 'coordinateDegreeBetween90', min: -90, max: 90)]
    public ?string $latitude = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Regex(pattern: '/^-?\d+(\.\d+)?$/', message: 'decimalNumber')]
    #[Assert\Range(notInRangeMessage: 'coordinateDegreeBetween180', min: -180, max: 180)]
    public ?string $longitude = null;

    #[Assert\NotBlank(message: 'fieldNotBlank')]
    #[Assert\Range(
        notInRangeMessage: 'valueMustBeBetween',
        min: 0,
        max: 19
    )]
    public int $zoom;

    /**
     * Initialize DTO from settings array.
     *
     * @param array<string, array{value: string|null, description?: string}> $data
     */
    public function __construct(array $data = [])
    {
        $this->latitude = $data[SettingName::MAP_CENTER_LATITUDE->value]['value'] ?? null;
        $this->longitude = $data[SettingName::MAP_CENTER_LONGITUDE->value]['value'] ?? null;
        $this->zoom = (int)($data[SettingName::MAP_CENTER_ZOOM->value]['value'] ?? null);
    }

    /**
     * Map the DTO back to an array for SettingsService.
     *
     * @return array<string, array{value: string|null}>
     */
    public function toArray(): array
    {
        return [
            SettingName::MAP_CENTER_LATITUDE->value => ['value' => $this->latitude],
            SettingName::MAP_CENTER_LONGITUDE->value => ['value' => $this->longitude],
            SettingName::MAP_CENTER_ZOOM->value => ['value' => (string) $this->zoom],
        ];
    }
}
