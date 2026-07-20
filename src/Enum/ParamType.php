<?php

declare(strict_types=1);

namespace App\Enum;

enum ParamType: string
{
    case JSON = 'json';
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case XML = 'xml';
}
