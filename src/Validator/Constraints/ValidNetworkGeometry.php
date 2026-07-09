<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidNetworkGeometry extends Constraint
{
    public string $messageSingle = 'polygonWarningMessage';
    public string $messageMultiple = 'polygonWarningMessageMultiple';

    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return ValidNetworkGeometryValidator::class;
    }
}
