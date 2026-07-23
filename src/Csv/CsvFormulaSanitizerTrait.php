<?php

declare(strict_types=1);

namespace App\Csv;

trait CsvFormulaSanitizerTrait
{
    /**
     * Neutralizes CSV/formula injection. If a field starts with a character
     * that Excel/Google Sheets/LibreOffice interpret as a formula trigger
     * (=, +, -, @, tab, CR), prefix it with a single quote so it's rendered
     * as plain text instead of being evaluated.
     */
    private function sanitizeCsvField(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }
}
