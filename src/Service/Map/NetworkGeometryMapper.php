<?php

declare(strict_types=1);

namespace App\Service\Map;

use App\Entity\Network;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\Polygon;

readonly class NetworkGeometryMapper
{
    /**
     * Builds Polygons from a Network's stored GeoJSON FeatureCollection geometry,
     * each with an InfoWindow showing the network's name and description.
     * Returns an empty array if the geometry is missing, malformed, or not a Polygon.
     *
     * @return list<Polygon>
     */
    public function buildPolygons(Network $network): array
    {
        $geometry = $network->getGeometry();

        if ($geometry === null || !isset($geometry['features']) || !is_array($geometry['features'])) {
            return [];
        }

        $polygons = [];

        foreach ($geometry['features'] as $feature) {
            $polygon = $this->buildPolygonFromFeature($feature, $network);

            if ($polygon !== null) {
                $polygons[] = $polygon;
            }
        }

        return $polygons;
    }

    /**
     * @param array<string, mixed> $feature
     */
    private function buildPolygonFromFeature(array $feature, Network $network): ?Polygon
    {
        $featureGeometry = $feature['geometry'] ?? null;

        if (!is_array($featureGeometry) || ($featureGeometry['type'] ?? null) !== 'Polygon') {
            return null;
        }

        $rings = $featureGeometry['coordinates'] ?? null;

        if (!is_array($rings) || !isset($rings[0]) || !is_array($rings[0])) {
            return null;
        }

        $points = $this->buildPointsFromRing($rings[0]);

        if (count($points) < 3) {
            return null;
        }

        return new Polygon(
            points: $points,
            infoWindow: new InfoWindow(
                headerContent: $this->buildHeaderContent($network),
                content: $this->buildInfoWindowContent($network),
            ),
        );
    }

    private function buildHeaderContent(Network $network): string
    {
        return '<strong class="coverage-map-popup-title">'
            . $this->escape($network->getName() ?? 'Site')
            . '</strong>';
    }

    private function buildInfoWindowContent(Network $network): string
    {
        $html = '';
        $description = $network->getDescription();

        // Add description if it exists
        if ($description !== null && $description !== '') {
            $html .= '<p class="coverage-map-popup-description">' . $this->escape($description) . '</p>';
        }

        return $html;
    }

    /**
     * @param array<int, mixed> $ring
     * @return list<Point>
     */
    private function buildPointsFromRing(array $ring): array
    {
        $points = [];

        foreach ($ring as $coordPair) {
            if (isset($coordPair[0], $coordPair[1]) && is_numeric($coordPair[0]) && is_numeric($coordPair[1])) {
                // GeoJSON stores [lng, lat]; Point expects (lat, lng)
                $points[] = new Point((float)$coordPair[1], (float)$coordPair[0]);
            }
        }

        return $points;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
