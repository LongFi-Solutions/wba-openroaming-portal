<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\FirewallType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\GetSettings;
use App\Service\UserDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserAccountDetailsController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly UserDataService $userDataService
    ) {
    }

    /**
     * Show User account details
     * @throws \DateMalformedStringException
     */
    #[Route('/dashboard/user/{id:user<\d+>}', name: 'admin_dashboard_user_show')]
    #[IsGranted(UserAuthenticationVoter::USERS_MANAGEMENT_READ)]
    public function showUser(
        User $user
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        return $this->render('dashboard/actions/show.html.twig', [
            'data' => $data,
            'context' => FirewallType::DASHBOARD->value,
            'currentUser' => $currentUser,
            'userData' => $this->userDataService->getData($user),
        ]);
    }
}


