<?php

declare(strict_types=1);

namespace App\Service\Map;

readonly class NetworkGeometryMapper
{
    /**
     * @param array<string, mixed>|null $geometry
     * @return array{minLat: float, minLng: float, maxLat: float, maxLng: float}|null
     */
    public function extractBoundingBox(?array $geometry): ?array
    {
        if ($geometry === null) {
            return null;
        }

        /** @var array<int, array{float, float}> $coords */
        $coords = [];
        $this->collectCoordinates($geometry, $coords);

        if ($coords === []) {
            return null;
        }

        $lats = array_column($coords, 1);
        $lngs = array_column($coords, 0);

        return [
            'minLat' => min($lats),
            'minLng' => min($lngs),
            'maxLat' => max($lats),
            'maxLng' => max($lngs),
        ];
    }

    /**
     * @param array<int, array{float, float}> $coords
     */
    private function collectCoordinates(mixed $node, array &$coords): void
    {
        if (!is_array($node)) {
            return;
        }

        if (isset($node['type'], $node['features']) && $node['type'] === 'FeatureCollection') {
            /** @var array<string, mixed> $feature */
            foreach ($node['features'] as $feature) {
                $this->collectCoordinates($feature, $coords);
            }
            return;
        }

        if (isset($node['type'], $node['geometry']) && $node['type'] === 'Feature') {
            $this->collectCoordinates($node['geometry'], $coords);
            return;
        }

        if (isset($node['type'], $node['coordinates'])) {
            $this->flattenCoordinates($node['coordinates'], $coords);
        }
    }

    /**
     * @param array<int, array{float, float}> $coords
     */
    private function flattenCoordinates(mixed $coordinates, array &$coords): void
    {
        if (!is_array($coordinates)) {
            return;
        }

        if (count($coordinates) === 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            $coords[] = [(float)$coordinates[0], (float)$coordinates[1]];
            return;
        }

        foreach ($coordinates as $nested) {
            $this->flattenCoordinates($nested, $coords);
        }
    }
}
