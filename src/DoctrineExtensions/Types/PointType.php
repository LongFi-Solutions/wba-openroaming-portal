<?php

namespace App\DoctrineExtensions\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class PointType extends Type
{
    public const string NAME = 'point';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT SRID 4326';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        return sprintf('ST_AsGeoJSON(%s)', $sqlExpr);
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        // options=1 + srid=4326 tells MySQL to interpret GeoJSON's [lng,lat]
        // order correctly for this geographic SRS — required or ST_Contains
        // will later throw "different SRIDs" errors against the column.
        return sprintf('ST_GeomFromGeoJSON(%s, 1, 4326)', $sqlExpr);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
