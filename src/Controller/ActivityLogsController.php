<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivityLogsController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
    ) {
    }

    /**
     * Activity Logs Page
     */
    #[Route('/dashboard/activityLogs', name: 'admin_dashboard_activity_logs')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function activityLogs(): Response
    {
        if (!$this->isGranted(UserAuthenticationVoter::ACTIVITY_LOGS_READ)) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            return $this->redirectToRoute('admin_user_show', ['id' => $currentUser->getId()]);
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/activity_logs.html.twig', [
            'data' => $data,
        ]);
    }
}
