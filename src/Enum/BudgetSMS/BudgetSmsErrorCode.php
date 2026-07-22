<?php

declare(strict_types=1);

namespace App\Enum\BudgetSMS;

enum BudgetSmsErrorCode: int
{
    case NOT_ENOUGH_CREDITS = 1001;
    case IDENTIFICATION_FAILED = 1002;
    case ACCOUNT_NOT_ACTIVE = 1003;
    case IP_NOT_ALLOWED = 1004;
    case NO_HANDLE = 1005;
    case NO_USERID = 1006;
    case NO_USERNAME = 1007;
    case MESSAGE_EMPTY = 2001;
    case SENDERID_NUMERIC_TOO_LONG = 2002;
    case SENDERID_ALPHA_TOO_LONG = 2003;
    case SENDERID_INVALID = 2004;
    case DESTINATION_TOO_SHORT = 2005;
    case DESTINATION_NOT_NUMERIC = 2006;
    case DESTINATION_EMPTY = 2007;
    case TEXT_NOT_OK = 2008;
    case PARAMETER_ISSUE = 2009;
    case DESTINATION_INVALID_FORMAT = 2010;
    case DESTINATION_INVALID = 2011;
    case MESSAGE_TOO_LONG = 2012;
    case MESSAGE_INVALID = 2013;
    case CUSTOMID_USED = 2014;
    case CHARSET_PROBLEM = 2015;
    case INVALID_UTF8 = 2016;
    case INVALID_SMSID = 2017;
    case NO_ROUTE = 3001;
    case NO_ROUTES_SETUP = 3002;
    case INVALID_DESTINATION_INTL = 3003;
    case SYSTEM_ERROR_CUSTOMID = 4001;
    case SYSTEM_ERROR_TEMP_RETRY = 4002;
    case SYSTEM_ERROR_TEMP = 4003;
    case SYSTEM_ERROR_TEMP_CONTACT = 4004;
    case SYSTEM_ERROR_PERMANENT = 4005;
    case GATEWAY_NOT_REACHABLE = 4006;
    case SYSTEM_ERROR_CONTACT = 4007;
    case SEND_ERROR = 5001;
    case WRONG_SMS_TYPE = 5002;
    case WRONG_OPERATOR = 5003;
    case UNKNOWN_ERROR = 6001;
    case NO_HLR_PROVIDER = 7001;
    case UNEXPECTED_HLR_RESULT = 7002;
    case BAD_NUMBER_FORMAT = 7003;
    case UNEXPECTED_ERROR = 7901;
    case HLR_PROVIDER_ERROR_1 = 7902;
    case HLR_PROVIDER_ERROR_2 = 7903;

    public function getMessage(): string
    {
        return match ($this) {
            self::NOT_ENOUGH_CREDITS => 'Not enough credits to send messages',
            self::IDENTIFICATION_FAILED => 'Identification failed — wrong credentials',
            self::ACCOUNT_NOT_ACTIVE => 'Account not active, contact BudgetSMS',
            self::IP_NOT_ALLOWED => "This server's IP address is not added to this BudgetSMS account",
            self::NO_HANDLE => 'No handle provided',
            self::NO_USERID => 'No User ID provided',
            self::NO_USERNAME => 'No username provided',
            self::MESSAGE_EMPTY => 'SMS message text is empty',
            self::SENDERID_NUMERIC_TOO_LONG => 'Numeric sender ID can be max. 16 digits',
            self::SENDERID_ALPHA_TOO_LONG => 'Alphanumeric sender ID can be max. 11 characters',
            self::SENDERID_INVALID => 'Sender ID is empty or invalid',
            self::DESTINATION_TOO_SHORT => 'Destination number is too short',
            self::DESTINATION_NOT_NUMERIC => 'Destination is not numeric',
            self::DESTINATION_EMPTY => 'Destination is empty',
            self::TEXT_NOT_OK => 'SMS text is not OK (check encoding)',
            self::PARAMETER_ISSUE => 'Parameter issue — check all mandatory parameters and encoding',
            self::DESTINATION_INVALID_FORMAT => 'Destination number is invalidly formatted',
            self::DESTINATION_INVALID => 'Destination is invalid',
            self::MESSAGE_TOO_LONG => 'SMS message text is too long',
            self::MESSAGE_INVALID => 'SMS message is invalid',
            self::CUSTOMID_USED => 'Custom SMS ID has already been used before',
            self::CHARSET_PROBLEM => 'Charset problem',
            self::INVALID_UTF8 => 'Invalid UTF-8 encoding',
            self::INVALID_SMSID => 'Invalid SMS ID',
            self::NO_ROUTE => 'No route to destination, contact BudgetSMS',
            self::NO_ROUTES_SETUP => 'No routes are set up for this account, contact BudgetSMS',
            self::INVALID_DESTINATION_INTL => 'Invalid destination — check international mobile number formatting',
            self::SYSTEM_ERROR_CUSTOMID => 'System error related to custom ID',
            self::SYSTEM_ERROR_TEMP_RETRY => 'Temporary system error — try again in 2–3 minutes',
            self::SYSTEM_ERROR_TEMP => 'Temporary system error',
            self::SYSTEM_ERROR_TEMP_CONTACT => 'Temporary system error, contact BudgetSMS',
            self::SYSTEM_ERROR_PERMANENT => 'Permanent system error',
            self::GATEWAY_NOT_REACHABLE => 'Gateway not reachable',
            self::SYSTEM_ERROR_CONTACT => 'System error, contact BudgetSMS',
            self::SEND_ERROR => 'Send error, contact BudgetSMS with the send details',
            self::WRONG_SMS_TYPE => 'Wrong SMS type',
            self::WRONG_OPERATOR => 'Wrong operator',
            self::UNKNOWN_ERROR => 'Unknown error',
            self::NO_HLR_PROVIDER => 'No HLR provider present, contact BudgetSMS',
            self::UNEXPECTED_HLR_RESULT => 'Unexpected results from HLR provider',
            self::BAD_NUMBER_FORMAT => 'Bad number format',
            self::UNEXPECTED_ERROR => 'Unexpected error, contact BudgetSMS',
            self::HLR_PROVIDER_ERROR_1, self::HLR_PROVIDER_ERROR_2 => 'HLR provider error, contact BudgetSMS',
        };
    }
}
