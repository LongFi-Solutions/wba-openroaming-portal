<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class PointType extends Type
{
    public const string POINT = 'point';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT';
    }

    public function getName(): string
    {
        return self::POINT;
    }
}