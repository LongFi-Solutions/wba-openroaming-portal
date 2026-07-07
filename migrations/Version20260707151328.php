<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707151328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bounding box columns to Network for viewport-based polygon queries';
    }

    /**
     * @throws Exception
     * @throws \JsonException
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Network
            ADD min_lat DOUBLE PRECISION DEFAULT NULL,
            ADD min_lng DOUBLE PRECISION DEFAULT NULL,
            ADD max_lat DOUBLE PRECISION DEFAULT NULL,
            ADD max_lng DOUBLE PRECISION DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_network_bbox ON Network (min_lat, max_lat, min_lng, max_lng)');

        // Backfill existing rows from their GeoJSON geometry
        $this->backfillBoundingBoxes();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_network_bbox ON Network');
        $this->addSql('ALTER TABLE Network
            DROP min_lat,
            DROP min_lng,
            DROP max_lat,
            DROP max_lng');
    }

    /**
     * @throws Exception
     * @throws \JsonException
     */
    private function backfillBoundingBoxes(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, geometry FROM Network WHERE geometry IS NOT NULL');

        foreach ($rows as $row) {
            $geoJson = json_decode((string) $row['geometry'], true, 512, JSON_THROW_ON_ERROR);
            $bbox = $this->extractBoundingBox($geoJson);

            if ($bbox === null) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE Network SET min_lat = ?, min_lng = ?, max_lat = ?, max_lng = ? WHERE id = ?',
                [$bbox['minLat'], $bbox['minLng'], $bbox['maxLat'], $bbox['maxLng'], $row['id']]
            );
        }
    }

    /**
     * @param array<string, mixed> $geoJson
     * @return array{minLat: float, minLng: float, maxLat: float, maxLng: float}|null
     */
    private function extractBoundingBox(array $geoJson): ?array
    {
        $coords = [];
        $this->collectCoordinates($geoJson, $coords);

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
     * Recursively walks a GeoJSON FeatureCollection/Feature/Geometry structure
     * and collects every [lng, lat] coordinate pair found.
     *
     * @param mixed $node
     * @param array<int, array{0: float, 1: float}> $coords
     */
    private function collectCoordinates(mixed $node, array &$coords): void
    {
        if (!is_array($node)) {
            return;
        }

        if (isset($node['type'], $node['features']) && $node['type'] === 'FeatureCollection') {
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
     * @param mixed $coordinates
     * @param array<int, array{0: float, 1: float}> $coords
     */
    private function flattenCoordinates(mixed $coordinates, array &$coords): void
    {
        if (!is_array($coordinates)) {
            return;
        }

        // A coordinate pair looks like [lng, lat] — two numeric leaves
        if (count($coordinates) === 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            $coords[] = [(float) $coordinates[0], (float) $coordinates[1]];
            return;
        }

        foreach ($coordinates as $nested) {
            $this->flattenCoordinates($nested, $coords);
        }
    }
}