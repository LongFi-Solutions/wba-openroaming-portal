<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class GeometryType extends Type
{
    public const GEOMETRY = 'geometry_spatial';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'GEOMETRY';
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = json_encode($value);
        }

        return sprintf("ST_GeomFromGeoJSON('%s', 1, 4326)", addslashes($value));
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        return $value;
    }

    public function getName(): string
    {
        return self::GEOMETRY;
    }

    public function canRequireSQLConversion(): bool
    {
        return true;
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_SRID(ST_GeomFromGeoJSON(%s), 4326)', $sqlExpr);
    }
}