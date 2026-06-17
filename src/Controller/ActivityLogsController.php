<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\ActivityLog\ActivityLogExporter;
use App\Service\ActivityLog\ExportFilters;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivityLogsController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly ActivityLogExporter $activityLogExporter,
        private readonly EventActions $eventActions,
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
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Event log
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::EXPORT_ACTIVITY_LOGS_REQUEST->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::FORMAT->value => $format
            ]
        );

        return $this->activityLogExporter->export(
            ExportFilters::fromRequest($request),
            $format,
        );
    }
}
