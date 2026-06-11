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
    case CHANGESET = 'changeset';
    case PERFORMED_ON_UUID = 'performed_on_uuid';
    case RESET_ATTEMPTS = 'reset_attempts';
    case LAST_RESET_ACCOUNT_PASSWORD_TIME = 'last_reset_account_password_time';
    case ADMIN_ACCOUNT_CREATED = 'admin_account_created';
    case FORMAT = 'format';
    case DOMAIN_ADDED = 'domain_added';
    case DOMAIN_REMOVED = 'domain_removed';
    case DOMAIN_EDITED_BEFORE = 'domain-edited-before';
    case DOMAIN_EDITED_AFTER = 'domain-edited-after';
    case DOMAIN_SOURCE_ADDED = 'domain-source-added';
    case DOMAIN_SOURCE_REMOVED = 'domain-source-removed';
    case DOMAIN_SOURCE_URL = 'domain-source-url';
    case DOMAIN_SOURCE_RESULT_STATUS = 'domain-source-result-status';
}
