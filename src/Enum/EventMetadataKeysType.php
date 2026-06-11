<?php

namespace App\Enum;

enum EventMetadataKeysType: string
{
    case IP = 'IP';
    case USER_AGENT = 'USER_AGENT';
    case PLATFORM = 'PLATFORM';
    case UUID = 'UUID';

}
