<?php

namespace App\Validator\Constraints;

use App\DTO\AccessPointDTO;
use Doctrine\DBAL\Connection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidAccessPointLocationValidator extends ConstraintValidator
{
    public function __construct(
        private Connection $connection
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidAccessPointLocation) {
            throw new UnexpectedTypeException($constraint, ValidAccessPointLocation::class);
        }

        if (!$value instanceof AccessPointDTO) {
            return;
        }

        if ($value->latitude === null || $value->longitude === null || !$value->network) {
            return;
        }

        $geoJson = $value->network->getGeometry();
        if ($geoJson === null || $geoJson === []) {
            $this->context->buildViolation($constraint->noGeometryMessage)
                ->atPath('network')
                ->addViolation();
            return;
        }

        $polygons = [];
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

            if ($geometry['type'] === 'Polygon') {
                $polygons[] = $geometry['coordinates'];
            } elseif ($geometry['type'] === 'MultiPolygon') {
                foreach ($geometry['coordinates'] as $coords) {
                    $polygons[] = $coords;
                }
            }
        }

        if ($polygons === []) {
            return;
        }

        $networkGeometryJson = json_encode([
            'type' => 'MultiPolygon',
            'coordinates' => $polygons
        ]);

        $pointJson = json_encode([
            'type' => 'Point',
            'coordinates' => [(float)$value->longitude, (float)$value->latitude]
        ]);

        $sql = '
        SELECT ST_Contains(ST_GeomFromGeoJSON(:network_geo), 
        ST_GeomFromGeoJSON(:point_geo))';

        try {
            $isInside = (bool) $this->connection->fetchOne($sql, [
                'network_geo' => $networkGeometryJson,
                'point_geo' => $pointJson,
            ]);

            if (!$isInside) {
                $this->context->buildViolation($constraint->message)
                    ->atPath('latitude')
                    ->addViolation();
            }
        } catch (\Exception $e) {
            $this->context->buildViolation('invalidGeometryFormat')
                ->atPath('latitude')
                ->addViolation();
        }
    }
}