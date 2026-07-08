<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class PointType extends Type
{
    public const POINT = 'point';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return $value;
    }

    public function getName(): string
    {
        return self::POINT;
    }
}