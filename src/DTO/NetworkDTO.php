<?php

namespace App\DTO;

use App\Entity\AccessPoint;
use App\Entity\Network;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\Constraints as AppAssert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class NetworkDTO
{

    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $name = null;
    public ?string $description = null;
    #[Assert\NotBlank(message: 'fieldCannotBeBlank')]
    public ?string $geometryJson = null;

    /** @var array<AccessPoint> */
    public array $accessPointsFromDatabase = [];


    #[Assert\Callback]
    public function validateGeometryContainsPoints(ExecutionContextInterface $context): void
    {
        if (!$this->geometryJson || empty($this->accessPointsFromDatabase)) {
            return;
        }

        $geoJson = json_decode($this->geometryJson, true);
        $polygonVertices = $geoJson['features'][0]['geometry']['coordinates'][0] ?? null;

        if (!$polygonVertices || count($polygonVertices) < 3) {
            return;
        }

        $pointsOutside = [];

        foreach ($this->accessPointsFromDatabase as $ap) {
            $location = $ap->getLocation();
            if ($location && isset($location['coordinates'])) {
                $apLng = (float)$location['coordinates'][0];
                $apLat = (float)$location['coordinates'][1];

                if (!$this->isPointInPolygon([$apLng, $apLat], $polygonVertices)) {
                    $pointsOutside[] = $ap->getSsid();
                }
            }
        }

        if (count($pointsOutside) > 0) {
            $pointsList = implode(', ', $pointsOutside);

            $message = count($pointsOutside) === 1
                ? 'polygonWarningMessage'
                : 'polygonWarningMessageMultiple';

            $context->buildViolation($message)
                ->setParameter('%s', $pointsList)
                ->atPath('geometryJson')
                ->addViolation();
        }
    }

    private function isPointInPolygon(array $point, array $polygonVertices): bool
    {
        $x = $point[0]; $y = $point[1]; $inside = false;
        $count = count($polygonVertices);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygonVertices[$i][0]; $yi = $polygonVertices[$i][1];
            $xj = $polygonVertices[$j][0]; $yj = $polygonVertices[$j][1];
            $intersect = (($yi > $y) != ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi + 0.000000001) + $xi);
            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }

    public function updateEntity(Network $network): Network
    {
        $network->setName($this->name);
        $network->setDescription($this->description);

        if ($this->geometryJson) {
            $network->setGeometry(json_decode($this->geometryJson, true));
        } else {
            $network->setGeometry(null);
        }

        $network->setUpdatedAt(new DateTimeImmutable());

        return $network;
    }
}