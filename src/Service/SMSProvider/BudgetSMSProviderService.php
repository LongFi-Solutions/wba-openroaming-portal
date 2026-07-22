<?php

namespace App\Service\SMSProvider;

use App\Entity\SMSProvider;
use App\Entity\User;
use App\Enum\ParamType;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class BudgetSMSProviderService implements SMSProviderInterface
{
    private const LIVE_API_URL = 'https://api.budgetsms.net/sendsms/';
    private const TEST_API_URL = 'https://api.budgetsms.net/testsms/';

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public static function sendSMS(SMSProvider $provider, string $message, User $user): string
    {
        $recipient = '+' . $user->getPhoneNumber()->getCountryCode() . $user->getPhoneNumber()->getNationalNumber();

        $queryParams = array_merge(
            self::getProviderParams($provider),
            [
                'to' => $recipient,
                'msg' => $message,
            ]
        );

        $apiUrl = self::resolveApiUrl($provider) . '?' . http_build_query($queryParams);

        $client = HttpClient::create();

        return $client->request('GET', $apiUrl)->getContent();
    }

    private static function resolveApiUrl(SMSProvider $provider): string
    {
        return $provider->isTestMode() ? self::TEST_API_URL : self::LIVE_API_URL;
    }

    /**
     * @return array<string, mixed>
     * @throws \JsonException
     */
    private static function getProviderParams(SMSProvider $provider): array
    {
        $params = [];
        foreach ($provider->getSmsProviderParams() as $param) {
            $value = $param->getValue();
            $type = $param->getType();

            $params[$param->getParamType()] = match ($type) {
                ParamType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                ParamType::JSON => json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR) ?? [],
                default => $value,
            };
        }

        return $params;
    }
}
