<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class PointType extends Type
{
    public const NAME = 'point';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT';
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_GeomFromGeoJSON(%s, 1, 4326)', $sqlExpr);
    }

    #[\Override]
    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        return sprintf('ST_AsGeoJSON(%s)', $sqlExpr);
    }
}
