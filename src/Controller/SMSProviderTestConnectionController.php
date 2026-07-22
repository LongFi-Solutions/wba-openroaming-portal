<?php

namespace App\Controller;

use App\Enum\SMSProviderType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\SMSProvider\BudgetSMS\BudgetSMSProviderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted(UserAuthenticationVoter::SMS_CONFIG_READ)]
class SMSProviderTestConnectionController extends AbstractController
{
    public function __construct(
        private readonly BudgetSMSProviderService $budgetSMSProviderService
    ) {
    }

    /**
     * Validates provider credentials by making a real (but harmless) test
     * request to the actual provider's API, before anything is saved.
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
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
        }

        $smsProviderTypeValue = $request->request->get('smsProviderType');

        if ($smsProviderTypeValue !== SMSProviderType::BUDGET_SMS->value) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Testing is not yet supported for this provider type.',
            ], 400);
        }

        $username = (string)$request->request->get('username', '');
        $userid = (string)$request->request->get('userid', '');
        $handle = (string)$request->request->get('handle', '');
        $from = (string)$request->request->get('from', '');

        try {
            $result = $this->budgetSMSProviderService->testCredentials($username, $userid, $handle, $from);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Could not reach BudgetSMS: ' . $e->getMessage(),
            ], 502);
        }

        return new JsonResponse([
            'success' => $result->success,
            'message' => $result->message,
            'errorCode' => $result->errorCode,
        ]);
    }
}