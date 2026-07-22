<?php

namespace App\Service\SMSProvider\BudgetSMS;

use App\Enum\BudgetSMS\BudgetSmsErrorCode;

final readonly class BudgetSMSTestResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?int $errorCode = null,
    ) {
    }

    public static function ok(string $message): self
    {
        return new self(true, $message);
    }

    public static function failed(int $errorCode, ?string $rawMessage = null): self
    {
        $mapped = BudgetSmsErrorCode::tryFrom($errorCode)?->getTranslationKey();

        return new self(
            success: false,
            message: $mapped ?? $rawMessage ?? 'Unknown error from BudgetSMS.',
            errorCode: $errorCode,
        );
    }
}
