<?php

namespace App\Controller;

use App\Enum\AdminRoleType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\ActivityLog\ActivityLogExporter;
use App\Service\ActivityLog\ExportFilters;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivityLogsController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserRepository $userRepository,
        private readonly EventRepository $eventRepository,
        private readonly ActivityLogExporter $activityLogExporter,
    ) {
    }

    /**
     * Activity Logs Page
     */
    #[Route('/dashboard/activity-logs', name: 'admin_dashboard_activity_logs')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    #[IsGranted(UserAuthenticationVoter::ACTIVITY_LOGS_READ)]
    public function activityLogs(): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/activity_logs.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route(
        '/dashboard/activity-log/export/{format}',
        name: 'admin_dashboard_activity_logs_export',
        requirements: ['format' => 'csv|json']
    )]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    #[IsGranted(UserAuthenticationVoter::ACTIVITY_LOGS_READ)]
    public function export(Request $request, string $format): Response
    {
        return $this->activityLogExporter->export(
            ExportFilters::fromRequest($request),
            $format,
        );
    }
}
