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

    /**
     * Returns the translation key for this error, under the `_sms` domain.
     * Callers are responsible for calling $translator->trans($key, [], '_sms').
     */
    public function getTranslationKey(): string
    {
        return match ($this) {
            self::NOT_ENOUGH_CREDITS => 'budgetSmsError.notEnoughCredits',
            self::IDENTIFICATION_FAILED => 'budgetSmsError.identificationFailed',
            self::ACCOUNT_NOT_ACTIVE => 'budgetSmsError.accountNotActive',
            self::IP_NOT_ALLOWED => 'budgetSmsError.ipNotAllowed',
            self::NO_HANDLE => 'budgetSmsError.noHandle',
            self::NO_USERID => 'budgetSmsError.noUserid',
            self::NO_USERNAME => 'budgetSmsError.noUsername',
            self::MESSAGE_EMPTY => 'budgetSmsError.messageEmpty',
            self::SENDERID_NUMERIC_TOO_LONG => 'budgetSmsError.senderIdNumericTooLong',
            self::SENDERID_ALPHA_TOO_LONG => 'budgetSmsError.senderIdAlphaTooLong',
            self::SENDERID_INVALID => 'budgetSmsError.senderIdInvalid',
            self::DESTINATION_TOO_SHORT => 'budgetSmsError.destinationTooShort',
            self::DESTINATION_NOT_NUMERIC => 'budgetSmsError.destinationNotNumeric',
            self::DESTINATION_EMPTY => 'budgetSmsError.destinationEmpty',
            self::TEXT_NOT_OK => 'budgetSmsError.textNotOk',
            self::PARAMETER_ISSUE => 'budgetSmsError.parameterIssue',
            self::DESTINATION_INVALID_FORMAT => 'budgetSmsError.destinationInvalidFormat',
            self::DESTINATION_INVALID => 'budgetSmsError.destinationInvalid',
            self::MESSAGE_TOO_LONG => 'budgetSmsError.messageTooLong',
            self::MESSAGE_INVALID => 'budgetSmsError.messageInvalid',
            self::CUSTOMID_USED => 'budgetSmsError.customIdUsed',
            self::CHARSET_PROBLEM => 'budgetSmsError.charsetProblem',
            self::INVALID_UTF8 => 'budgetSmsError.invalidUtf8',
            self::INVALID_SMSID => 'budgetSmsError.invalidSmsId',
            self::NO_ROUTE => 'budgetSmsError.noRoute',
            self::NO_ROUTES_SETUP => 'budgetSmsError.noRoutesSetup',
            self::INVALID_DESTINATION_INTL => 'budgetSmsError.invalidDestinationIntl',
            self::SYSTEM_ERROR_CUSTOMID => 'budgetSmsError.systemErrorCustomId',
            self::SYSTEM_ERROR_TEMP_RETRY => 'budgetSmsError.systemErrorTempRetry',
            self::SYSTEM_ERROR_TEMP => 'budgetSmsError.systemErrorTemp',
            self::SYSTEM_ERROR_TEMP_CONTACT => 'budgetSmsError.systemErrorTempContact',
            self::SYSTEM_ERROR_PERMANENT => 'budgetSmsError.systemErrorPermanent',
            self::GATEWAY_NOT_REACHABLE => 'budgetSmsError.gatewayNotReachable',
            self::SYSTEM_ERROR_CONTACT => 'budgetSmsError.systemErrorContact',
            self::SEND_ERROR => 'budgetSmsError.sendError',
            self::WRONG_SMS_TYPE => 'budgetSmsError.wrongSmsType',
            self::WRONG_OPERATOR => 'budgetSmsError.wrongOperator',
            self::UNKNOWN_ERROR => 'budgetSmsError.unknownError',
            self::NO_HLR_PROVIDER => 'budgetSmsError.noHlrProvider',
            self::UNEXPECTED_HLR_RESULT => 'budgetSmsError.unexpectedHlrResult',
            self::BAD_NUMBER_FORMAT => 'budgetSmsError.badNumberFormat',
            self::UNEXPECTED_ERROR => 'budgetSmsError.unexpectedError',
            self::HLR_PROVIDER_ERROR_1, self::HLR_PROVIDER_ERROR_2 => 'budgetSmsError.hlrProviderError',
        };
    }
}
