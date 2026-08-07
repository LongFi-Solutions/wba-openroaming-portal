<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class MySQLGeometryType extends Type
{
    public const REVISOR_GEOMETRY = 'revisor_geometry';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'GEOMETRY';
    }

    /**
     * @param mixed $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    /**
     * @param mixed $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    public function getName(): string
    {
        return self::REVISOR_GEOMETRY;
    }

    /**
     * @param string $sqlExpr
     */
    #[\Override]
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_GeomFromGeoJSON(%s, 1, 4326)', $sqlExpr);
    }

    /**
     * @param string $sqlExpr
     * @param AbstractPlatform $platform
     */
    #[\Override]
    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        return sprintf('ST_AsGeoJSON(%s)', $sqlExpr);
    }
}
