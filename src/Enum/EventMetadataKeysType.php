<?php

namespace App\Enum;

enum EventMetadataKeysType: string
{
    case IP = 'ip';
    case USER_AGENT = 'user_agent';
    case PLATFORM = 'platform';
    case UUID = 'uuid';
    case DOWNLOADED_PROFILE_TYPE  = 'type';
    case REGISTRATION_TYPE = 'registration_type';
    case VERIFICATION_ATTEMPTS = 'verification_attempts';
    case PERFORMED_ON_UUID = 'performed_on_uuid';
    case RESET_ATTEMPTS = 'reset_attempts';
    case LAST_RESET_ACCOUNT_PASSWORD_TIME = 'last_reset_account_password_time';
}
