<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidAccessPointLocation extends Constraint
{
    public string $message = 'pointOutsideNetworkPolygon';
    public string $noGeometryMessage = 'networkHasNoGeometry';

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
