<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\SMSProviderType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\SMSProvider\BudgetSMS\BudgetSMSProviderService;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted(UserAuthenticationVoter::SMS_CONFIG_READ)]
class SMSProviderTestConnectionController extends AbstractController
{
    public function __construct(
        private readonly BudgetSMSProviderService $budgetSMSProviderService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Sends a real test SMS to a phone number typed by the admin, using
     * the provider credentials currently in the form (not yet saved).
     */
    #[Route(
        '/dashboard/settings/sms/providers/test-connection',
        name: 'admin_dashboard_settings_sms_providers_test_connection',
        methods: ['POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function testConnection(Request $request): JsonResponse
    {
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('sms-provider-test-connection', is_string($token) ? $token : null)) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('sms_provider_test.invalid_csrf', [], 'controllers'),
            ], Response::HTTP_FORBIDDEN);
        }

        $smsProviderTypeValue = $request->request->get('smsProviderType');

        if ($smsProviderTypeValue !== SMSProviderType::BUDGET_SMS->value) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('sms_provider_test.unsupported_provider', [], 'controllers'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $username = (string)$request->request->get('username', '');
        $userid = (string)$request->request->get('userid', '');
        $handle = (string)$request->request->get('handle', '');
        $from = (string)$request->request->get('from', '');

        // These two mirror the "country" + "number" split used by the
        // registration phone widget.
        $countryIso = (string)$request->request->get('country', '');
        $nationalNumber = (string)$request->request->get('number', '');

        if ($nationalNumber === '') {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('sms_provider_test.empty_phone_number', [], 'controllers'),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $to = PhoneNumberUtil::getInstance()->parse($nationalNumber, $countryIso ?: null);
        } catch (NumberParseException) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('sms_provider_test.invalid_phone_number', [], 'controllers'),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->budgetSMSProviderService->testCredentials($username, $userid, $handle, $from, $to);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans(
                    'sms_provider_test.unreachable_provider',
                    ['%error%' => $e->getMessage()],
                    'controllers'
                ),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'success' => $result->success,
            'message' => $result->message,
            'errorCode' => $result->errorCode,
        ]);
    }
}
