<?php

namespace App\Service;

use App\Entity\SMSProvider;
use App\Entity\User;
use App\Enum\ParamType;
use App\Enum\SettingName;
use App\Enum\SMSResponse;
use App\Repository\SettingRepository;
use App\Repository\SMSProviderRepository;
use App\Repository\UserRepository;
use DateTime;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class SendSMS
{
    /**
     * SendSMS constructor.
     */
    public function __construct(
        private SettingRepository $settingRepository,
        private SMSProviderRepository $smsProviderRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function sendSmsNoValidation(User $user, string $message): string
    {
        $recipient = "+" .
            $user->getPhoneNumber()->getCountryCode() .
            $user->getPhoneNumber()->getNationalNumber();

        $provider = $this->getActiveProvider();

        // Check if the user can regenerate the SMS code
        $client = HttpClient::create();
        $messageLength = $this->verifyMessageLength($message);
        if ($messageLength) {
            $user->setTwoFACode((string)random_int(100000, 999999));
            $user->setTwoFACodeGeneratedAt(new DateTime());
            $user->setTwoFAcodeIsActive(true);
            $this->userRepository->save($user, true);

            $message = 'Verification code is: ' . $user->getTwoFACode();
        }

        // Every provider-specific credential (username, userid, handle, from, ...) comes
        // straight from that provider's SMSProviderParam rows — nothing is hardcoded here,
        // so adding/switching providers is a data change, not a code change.
        $queryParams = array_merge(
            $this->getProviderParams($provider),
            [
                'to' => $recipient,
                'msg' => $message,
            ]
        );

        $apiUrl = $provider->getAddress() . '?' . http_build_query($queryParams);
        $response = $client->request('GET', $apiUrl);

        // Handle the API response as needed
        $response->getStatusCode();
        $response->getContent();

        if ($messageLength) {
            return SMSResponse::SMS_SUCCESS_CODE->value;
        }
        return SMSResponse::SMS_SUCCESS_LINK->value;
    }

    public function verifyMessageLength(string $message): bool
    {
        return strlen($message) > 612;
    }

    /**
     * Resolves which SMSProvider is active by reading its name off the
     * SMS_ACTIVE_PROVIDER setting, then loading the matching SMSProvider entity.
     */
    private function getActiveProvider(): SMSProvider
    {
        $activeProviderName = $this->settingRepository
            ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value])
            ?->getValue();

        if ($activeProviderName === null || $activeProviderName === '') {
            throw new RuntimeException(
                'No active SMS provider configured — set a value for the SMS_ACTIVE_PROVIDER setting.'
            );
        }

        $provider = $this->smsProviderRepository->findOneBy(['name' => $activeProviderName]);

        if ($provider === null) {
            throw new RuntimeException(
                sprintf(
                    'Active SMS provider "%s" (from SMS_ACTIVE_PROVIDER) has no matching SMSProvider entity.',
                    $activeProviderName
                )
            );
        }

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function getProviderParams(SMSProvider $provider): array
    {
        $params = [];
        foreach ($provider->getSmsProviderParams() as $param) {
            $value = $param->getValue();
            $type = $param->getType();

            $parsedValue = match ($type) {
                ParamType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                ParamType::JSON => json_decode((string)$value, true, 512, JSON_THROW_ON_ERROR) ?? [],
                default => $value, // STRING
            };

            $params[$param->getParamType()] = $parsedValue;
        }

        return $params;
    }
}
