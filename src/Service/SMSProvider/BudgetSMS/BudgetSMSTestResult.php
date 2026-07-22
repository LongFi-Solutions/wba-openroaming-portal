<?php

namespace App\Service\SMSProvider\BudgetSMS;

final readonly class BudgetSMSTestResult
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {
    }
}
