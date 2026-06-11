<?php

namespace App\Enum;

enum EventMetadataKeysType: string
{
    case IP = 'ip';
    case USER_AGENT = 'user_agent';
    case PLATFORM = 'platform';
    case UUID = 'uuid';
    case DOWNLOADED_PROFILE_TYPE  = 'type';
    case VERIFICATION_ATTEMPTS = 'verification_attempts';
}
