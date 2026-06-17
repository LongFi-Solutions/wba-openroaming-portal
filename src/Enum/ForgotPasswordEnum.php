<?php

declare(strict_types=1);

namespace App\Enum;

enum ForgotPasswordEnum: string
{
    case SUCCESS = 'success';
    case MESSAGE_TYPE  = 'messageType';
    case TIME_LEFT = 'timeLeft';
    case ATTEMPTS_EXCEEDED = 'attemptsExceeded';
    case TIME_BETWEEN_REQUESTS = 'timeBetweenRequests';
}
