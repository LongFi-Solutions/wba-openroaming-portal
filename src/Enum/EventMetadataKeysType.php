<?php

declare(strict_types=1);

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
    case PERFORMED_ON_ID = 'performed_on_id';
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
    case VALIDATION_SUCCESS = 'validation-success';
    case IS_EV_CERTIFICATE = 'is_ev_certificate';
    case VALIDATION_ERRORS = 'validation_errors';
    case OLD_DATA = 'old_data';
    case NEW_DATA = 'new_data';
    case USER_OLD_DATA = 'user_old_data';
    case USER_NEW_DATA = 'user_new_data';
    case FIRST_NAME = 'first_name';
    case LAST_NAME = 'last_name';
}
