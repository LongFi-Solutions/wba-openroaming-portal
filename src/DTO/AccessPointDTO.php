<?php

namespace App\DTO;

use App\Entity\AccessPoint;
use App\Entity\Network;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
    #[Assert\Regex(
        pattern: '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
        message: 'invalidMacAddressFormat'
    )]
    public ?string $macAddress = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $vendor = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $model = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    public ?string $standard = null;

    #[Assert\Length(max: 255, maxMessage: 'maxCharacters')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9\-_:]+$/', message: 'invalidSerialNumberFormat')]
    public ?string $serialNumber = null;

    #[Assert\Regex(pattern: '/^-?\d+(\.\d+)?$/', message: 'decimalNumber')]
    #[Assert\Range(notInRangeMessage: 'cordinateDeegreBteween90', min: -90, max: 90)]
    public ?string $latitude = null;

    #[Assert\Regex(pattern: '/^-?\d+(\.\d+)?$/', message: 'decimalNumber')]
    #[Assert\Range(notInRangeMessage: 'cordinateDeegreBteween180', min: -180, max: 180)]
    public ?string $longitude = null;


    #[Assert\Type(type: 'float', message: 'decimalNumber')]
    #[Assert\Range(notInRangeMessage: 'invalidAltitudeMsl', min: -500, max: 9000)]
    public ?float $altitudeMsl = null;

    #[Assert\Type(type: 'float', message: 'decimalNumber')]
    #[Assert\PositiveOrZero(message: 'altitudeAglCannotBeNegative')]
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

        $accessPoint->setUpdatedAt(new DateTimeImmutable());

        return $accessPoint;
    }

    #[Assert\Callback]
    public function validatePointIsInsideNetworkGeometry(ExecutionContextInterface $context): void
    {
        if ($this->latitude === null || $this->longitude === null) {
            return;
        }

        if (!$this->network instanceof Network) {
            $context->buildViolation('fieldCannotBeBlank')
                ->atPath('network')
                ->addViolation();
            return;
        }

        $geoJson = $this->network->getGeometry();

        if ($geoJson === null || $geoJson === []) {
            $context->buildViolation('networkHasNoGeometry')
                ->atPath('network')
                ->addViolation();
            return;
        }

        $allPolygons = [];
        $features = [];

        if (isset($geoJson['type'])) {
            if ($geoJson['type'] === 'FeatureCollection' && isset($geoJson['features'])) {
                $features = $geoJson['features'];
            } elseif ($geoJson['type'] === 'Feature') {
                $features = [$geoJson];
            } else {
                $features = [['geometry' => $geoJson]];
            }
        }

        foreach ($features as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (!$geometry) {
                continue;
            }

            $type = $geometry['type'] ?? '';
            $coordinates = $geometry['coordinates'] ?? [];

            if ($type === 'Polygon' && isset($coordinates[0])) {
                $allPolygons[] = $coordinates[0];
            } elseif ($type === 'MultiPolygon') {
                foreach ($coordinates as $polygonCoords) {
                    if (isset($polygonCoords[0])) {
                        $allPolygons[] = $polygonCoords[0];
                    }
                }
            }
        }

        if ($allPolygons === []) {
            return;
        }
        $isInsideAny = array_any($allPolygons, fn($vertices) => $this->isPointInPolygon([$this->longitude, $this->latitude], $vertices));

        if (!$isInsideAny) {
            $context->buildViolation('pointOutsideNetworkPolygon')
                ->atPath('latitude')
                ->addViolation();
        }
    }

    private function isPointInPolygon(array $point, array $polygonVertices): bool
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $count = count($polygonVertices);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygonVertices[$i][0];
            $yi = $polygonVertices[$i][1];
            $xj = $polygonVertices[$j][0];
            $yj = $polygonVertices[$j][1];

            if (($yi > $y !== $yj > $y && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
