<?php

declare(strict_types=1);

namespace App\Enum;

enum ExportFileType: string
{
    case CSV = 'csv';
    case JSON = 'json';
}
