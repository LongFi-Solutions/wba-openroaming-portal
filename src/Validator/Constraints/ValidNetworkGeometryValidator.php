<?php

namespace App\Validator\Constraints;

use App\DTO\NetworkDTO;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JsonException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidNetworkGeometryValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    /**
     * @throws JsonException
     * @throws Exception
     */
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

        $geoJsonArr = json_decode($value->geometryJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($geoJsonArr)) {
            return;
        }

        $polygons = [];

        if (isset($geoJsonArr['type'], $geoJsonArr['features']) && $geoJsonArr['type'] === 'FeatureCollection') {
            foreach ($geoJsonArr['features'] as $feature) {
                $geometry = $feature['geometry'] ?? null;
                if (!$geometry) {
                    continue;
                }

                if ($geometry['type'] === 'Polygon') {
                    $polygons[] = $geometry['coordinates'];
                } elseif ($geometry['type'] === 'MultiPolygon') {
                    foreach ($geometry['coordinates'] as $coords) {
                        $polygons[] = $coords;
                    }
                }
            }
        } elseif (isset($geoJsonArr['type']) && $geoJsonArr['type'] === 'Polygon') {
            $polygons[] = $geoJsonArr['coordinates'];
        } elseif (isset($geoJsonArr['type']) && $geoJsonArr['type'] === 'MultiPolygon') {
            $polygons = $geoJsonArr['coordinates'];
        }

        if ($polygons === []) {
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
                  ST_GeomFromGeoJSON(:new_geometry), 
                  ST_GeomFromGeoJSON(location)
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
}