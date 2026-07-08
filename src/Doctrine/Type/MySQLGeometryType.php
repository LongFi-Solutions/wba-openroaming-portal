<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class MySQLGeometryType extends Type
{
    public const REVISOR_GEOMETRY = 'revisor_geometry';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'GEOMETRY';
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    public function getName(): string
    {
        return self::REVISOR_GEOMETRY;
    }

    public function canRequireSQLConversion(): bool
    {
        return true;
    }


    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_GeomFromText(%s, 4326)', $sqlExpr);
    }
}