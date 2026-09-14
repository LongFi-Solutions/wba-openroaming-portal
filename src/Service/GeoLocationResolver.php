<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use GeoIp2\Database\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

readonly class GeoLocationResolver
{
    private const string DATABASE_PATH = __DIR__ . '/../../geoLiteDB/GeoLite2-City.mmdb';

    /**
     * Resolves approximate coordinates from an IP using the GeoLite2 database.
     *
     * @return array{0: float, 1: float}|null [latitude, longitude], or null if unavailable
     */
    public function getCoordinatesFromIp(string $ip): ?array
    {
        if (!file_exists(self::DATABASE_PATH)) {
            return null;
        }

        try {
            $reader = new Reader(self::DATABASE_PATH);
            $record = $reader->city($ip);

            if ($record->location->latitude === null || $record->location->longitude === null) {
                return null;
            }

            return [$record->location->latitude, $record->location->longitude];
        } catch (InvalidDatabaseException | Exception) {
            return null;
        }
    }
}
