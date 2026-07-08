<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\DTO\NetworkDTO;
use Doctrine\DBAL\Connection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidNetworkGeometryValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidNetworkGeometry) {
            throw new UnexpectedTypeException($constraint, ValidNetworkGeometry::class);
        }

        if (!$value instanceof NetworkDTO) {
            return;
        }

        if (!$value->geometryJson || !$value->networkId) {
            return;
        }

        try {
            $geoJsonArr = json_decode($value->geometryJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->context->buildViolation('invalidGeometryFormat')
                ->atPath('geometryJson')
                ->addViolation();
            return;
        }

        if (!is_array($geoJsonArr)) {
            $this->context->buildViolation('invalidGeometryFormat')
                ->atPath('geometryJson')
                ->addViolation();
            return;
        }

        $polygons = [];

        if (isset($geoJsonArr['type'], $geoJsonArr['features']) && $geoJsonArr['type'] === 'FeatureCollection') {
            foreach ($geoJsonArr['features'] as $feature) {
                $geometry = $feature['geometry'] ?? null;
                if (!is_array($geometry)) {
                    continue;
                }

                $type = $geometry['type'] ?? '';
                $coordinates = $geometry['coordinates'] ?? null;

                if (!is_array($coordinates)) {
                    continue;
                }

                if ($type === 'Polygon') {
                    $polygons[] = $coordinates;
                } elseif ($type === 'MultiPolygon') {
                    foreach ($coordinates as $coords) {
                        if (is_array($coords)) {
                            $polygons[] = $coords;
                        }
                    }
                }
            }
        } else {
            $type = $geoJsonArr['type'] ?? '';
            $coordinates = $geoJsonArr['coordinates'] ?? null;

            if (is_array($coordinates)) {
                if ($type === 'Polygon') {
                    $polygons[] = $coordinates;
                } elseif ($type === 'MultiPolygon') {
                    $polygons = $coordinates;
                }
            }
        }

        if ($polygons === [] || !is_array($polygons)) {
            $this->context->buildViolation('invalidGeometryFormat')
                ->atPath('geometryJson')
                ->addViolation();
            return;
        }

        if (!$this->validateCoordinatesBounds($polygons)) {
            $this->context->buildViolation('invalidCoordinateBounds')
            ->atPath('geometryJson')
                ->addViolation();
            return;
        }

        $cleanGeometryJson = json_encode([
            'type' => 'MultiPolygon',
            'coordinates' => $polygons
        ], JSON_THROW_ON_ERROR);

        $sql = '
            SELECT ssid 
            FROM AccessPoint 
            WHERE network_id = :network_id 
              AND ST_Contains(
                  ST_GeomFromGeoJSON(:new_geometry, 1, 4326), 
                  location
              ) = 0
        ';

        try {
            $pointsOutside = $this->connection->fetchFirstColumn($sql, [
                'network_id' => $value->networkId,
                'new_geometry' => $cleanGeometryJson,
            ]);
        } catch (\Exception) {
            $this->context->buildViolation('invalidGeometryFormat')
                ->atPath('geometryJson')
                ->addViolation();
            return;
        }

        if (count($pointsOutside) > 0) {
            $pointsList = implode(', ', $pointsOutside);

            $message = count($pointsOutside) === 1
                ? $constraint->messageSingle
                : $constraint->messageMultiple;

            $this->context->buildViolation($message)
                ->setParameter('%s', $pointsList)
                ->atPath('geometryJson')
                ->addViolation();
        }
    }

    private function validateCoordinatesBounds(array $coordinates): bool
    {
        foreach ($coordinates as $item) {
            if (!is_array($item)) {
                return false;
            }

            if (isset($item[0], $item[1]) && !is_array($item[0]) && !is_array($item[1])) {
                $lng = $item[0];
                $lat = $item[1];

                if (!is_numeric($lng) || !is_numeric($lat)) {
                    return false;
                }

                if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                    return false;
                }
                continue;
            }

            if (!$this->validateCoordinatesBounds($item)) {
                return false;
            }
        }

        return true;
    }
}