<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidNetworkGeometry extends Constraint
{
    public string $messageSingle = 'polygonWarningMessage';
    public string $messageMultiple = 'polygonWarningMessageMultiple';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
