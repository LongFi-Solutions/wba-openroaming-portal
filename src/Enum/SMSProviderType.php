<?php

namespace App\Enum;

use App\Service\SMSProvider\BudgetSMSProviderService;
use App\Service\SMSProvider\SMSProviderInterface;

enum SMSProviderType: string
{
    case BUDGET_SMS = 'budgetsms';

    /**
     * @return class-string<SMSProviderInterface>
     */
    public function getServiceClass(): string
    {
        return match ($this) {
            self::BUDGET_SMS => BudgetSMSProviderService::class,
        };
    }
}
