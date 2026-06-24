<?php

namespace App\DTO;

use App\Entity\AccessPoint;
use App\Entity\Network;
use Symfony\Component\Validator\Constraints as Assert;

class AccessPointDTO
{
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?Network $network = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $ssid = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $macAddress = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $vendor = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $model = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $standard = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $serialNumber = null;

    #[Assert\Range(min: -90, max: 90, notInRangeMessage: 'cordinateDeegreBteween90')]
    public ?string $latitude = null;

    #[Assert\Range(min: -180, max: 180, notInRangeMessage: 'cordinateDeegreBteween180')]
    public ?string $longitude = null;


    #[Assert\Type(type: 'float', message: 'decimalNumber')]
    public ?float $altitudeMsl = null;

    #[Assert\Type(type: 'float', message: 'decimalNumber')]
    public ?float $altitudeAgl = null;


    public static function createFromEntity(AccessPoint $accessPoint): self
    {
        $dto = new self();
        $dto->network = $accessPoint->getNetwork();
        $dto->name = $accessPoint->getName();
        $dto->ssid = $accessPoint->getSsid();
        $dto->macAddress = $accessPoint->getMacAddress();
        $dto->vendor = $accessPoint->getVendor();
        $dto->model = $accessPoint->getModel();
        $dto->standard = $accessPoint->getStandard();
        $dto->serialNumber = $accessPoint->getSerialNumber();
        $dto->altitudeMsl = $accessPoint->getAltitudeMsl();
        $dto->altitudeAgl = $accessPoint->getAltitudeAgl();

        $location = $accessPoint->getLocation();
        if ($location && isset($location['coordinates']) && is_array($location['coordinates'])) {
            $dto->longitude = $location['coordinates'][0] ?? null;
            $dto->latitude = $location['coordinates'][1] ?? null;
        }

        return $dto;
    }

    public function updateEntity(AccessPoint $accessPoint): AccessPoint
    {
        $accessPoint->setNetwork($this->network);
        $accessPoint->setName($this->name);
        $accessPoint->setSsid($this->ssid ?? '');
        $accessPoint->setMacAddress($this->macAddress);
        $accessPoint->setVendor($this->vendor);
        $accessPoint->setModel($this->model);
        $accessPoint->setStandard($this->standard);
        $accessPoint->setSerialNumber($this->serialNumber);
        $accessPoint->setAltitudeMsl($this->altitudeMsl);
        $accessPoint->setAltitudeAgl($this->altitudeAgl);

        if ($this->latitude !== null && $this->longitude !== null) {
            $exactLng = (float) number_format((float)$this->longitude, 7, '.', '');
            $exactLat = (float) number_format((float)$this->latitude, 7, '.', '');

            $accessPoint->setLocation([
                'type' => 'Point',
                'coordinates' => [$exactLng, $exactLat]
            ]);
        } else {
            $accessPoint->setLocation(null);
        }

        $accessPoint->setUpdatedAt(new \DateTimeImmutable());

        return $accessPoint;
    }
}