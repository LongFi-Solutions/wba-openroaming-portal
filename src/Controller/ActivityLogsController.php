<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Repository\EventRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\GetSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivityLogsController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly GetSettings $getSettings,
    ) {
    }

    /**
     * Activity Logs Page
     */
    #[Route('/dashboard/activityLogs', name: 'admin_dashboard_activity_logs')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function activityLogs(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] ?int $count = 10
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Redirect to User Profile
        if (!$this->isGranted(UserAuthenticationVoter::ACTIVITY_LOGS_READ)) {
            return $this->redirectToRoute('admin_user_show', ['id' => $currentUser->getId()]);
        }

        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $searchTerm = $request->query->get('u');

        $filter = $request->query->get('filter', 'all'); // Default filter

        $rawLogs = $this->eventRepository->searchWithFilter($filter, $sort, $order, $searchTerm);

        // Perform pagination manually
        $totalLogs = count($rawLogs);
        $totalPages = ceil($totalLogs / $count);
        $offset = ($page - 1) * $count;
        $logs = array_slice($rawLogs, $offset, $count);

        $eventCounts = $this->eventRepository->countByEventGroup();

        return $this->render('dashboard/activity_logs.html.twig', [
            'logs' => $logs,
            'eventCounts' => $eventCounts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'searchTerm' => $searchTerm,
            'activeFilter' => $filter,
            'activeSort' => $sort,
            'activeOrder' => $order,
            'count' => $count,
            'data' => $data,
        ]);
    }
}