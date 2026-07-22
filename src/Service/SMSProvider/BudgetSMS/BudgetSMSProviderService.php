<?php

namespace App\Service\SMSProvider\BudgetSMS;

use App\Entity\SMSProvider;
use App\Entity\User;
use App\Enum\BudgetSMS\BudgetSmsErrorCode;
use App\Enum\ParamType;
use App\Service\SMSProvider\SMSProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BudgetSMSProviderService implements SMSProviderInterface
{
    private const string LIVE_API_URL = 'https://api.budgetsms.net/sendsms/';
    private const string TEST_API_URL = 'https://api.budgetsms.net/testsms/';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

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

    /**
     * Validates a set of BudgetSMS credentials by making a real request to their
     * testsms endpoint — this simulates a send (no credit deducted, no message
     * actually delivered), so it's safe to call before a provider is even saved.
     * Always hits the TEST endpoint regardless of the provider's own testMode
     * setting: this is purely a credentials check, never a real send.
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testCredentials(
        string $username,
        string $userid,
        string $handle,
        string $from
    ): BudgetSMSTestResult {
        $queryParams = [
            'username' => $username,
            'userid' => $userid,
            'handle' => $handle,
            'from' => $from,
            // Dummy but validly-formatted destination/message — only the
            // credentials themselves are being verified here, not delivery.
            'to' => '351910000000',
            'msg' => 'Test message from provider configuration',
        ];

        $apiUrl = self::TEST_API_URL . '?' . http_build_query($queryParams);

        $client = HttpClient::create();
        $response = $client->request('GET', $apiUrl)->getContent();

        return $this->parseResponse($response);
    }

    /**
     * BudgetSMS replies in plain text, not JSON — "OK <smsid>" on success,
     * "ERR <code>" on failure, per their HTTP API Specification.
     */
    private function parseResponse(string $response): BudgetSMSTestResult
    {
        $response = trim($response);

        if (str_starts_with($response, 'OK')) {
            return new BudgetSMSTestResult(
                true,
                $this->translator->trans('budgetSmsSuccess.credentialsVerified', [], '_sms')
            );
        }

        if (preg_match('/ERR\s*(\d+)/', $response, $matches)) {
            $code = (int)$matches[1];
            $errorCode = BudgetSmsErrorCode::tryFrom($code);

            $message = $errorCode !== null
                ? $this->translator->trans($errorCode->getTranslationKey(), [], '_sms')
                : $this->translator->trans('budgetSmsError.unknownCode', ['%code%' => $code], '_sms');

            return new BudgetSMSTestResult(false, $message, $code);
        }

        return new BudgetSMSTestResult(
            false,
            $this->translator->trans('budgetSmsError.unexpectedResponse', ['%response%' => $response], '_sms')
        );
    }

    private function resolveApiUrl(SMSProvider $provider): string
    {
        return $provider->isTestMode() ? self::TEST_API_URL : self::LIVE_API_URL;
    }

    /**
     * @return array<string, mixed>
     * @throws \JsonException
     */
    private function getProviderParams(SMSProvider $provider): array
    {
        $params = [];
        foreach ($provider->getSmsProviderParams() as $param) {
            $value = $param->getValue();
            $type = $param->getType();

            $params[$param->getParamType()] = match ($type) {
                ParamType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                ParamType::JSON => json_decode(
                    (string)$value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ) ?? [],
                default => $value,
            };
        }

        return $params;
    }
}
