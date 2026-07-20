<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValueMatchesParamType extends Constraint
{
    public string $jsonMessage = 'valueMustBeValidJson';
    public string $booleanMessage = 'valueMustBeValidBoolean';
    public string $xmlMessage = 'valueMustBeValidXml';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
