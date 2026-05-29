<?php

namespace App\Controller;

use App\DTO\UserAddDTO;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\FirewallType;
use App\Enum\PlatformMode;
use App\Form\UserAddType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\UserCreationService;
use App\Service\UserDataService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminManagementController extends AbstractController
{
    public function __construct(
        private readonly EventActions $eventActions,
        private readonly EntityManagerInterface $entityManager,
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly UserCreationService $userCreationService,
        private readonly UserDataService $userDataService,
    ) {
    }

    /**
     * Show Admin account details
     * @throws \DateMalformedStringException
     */
    #[Route('/dashboard/admin/{id:user<\d+>}', name: 'admin_dashboard_admin_show')]
    #[IsGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_READ)]
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
            'currentUser' => $currentUser,
            'userData' => $this->userDataService->getData($user)
        ]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/dashboard/admin/add', name: 'admin_dashboard_add_admin')]
    #[IsGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE)]
    public function addUsers(Request $request): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Create & handle form
        $userAddDTO = new UserAddDTO();
        $form = $this->createForm(UserAddType::class, $userAddDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Convert DTO → Entity data before creation
            $newUser = $this->userCreationService->createAdminUser($userAddDTO);

            // Flash message
            $this->addFlash(
                'success',
                $this->translator->trans('addedNewUser', [
                    '%uuid%' => $newUser->getUuid(),
                ], 'controllers')
            );

            $eventMetaData = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'userAddedBy' => $newUser->getUuid(),
                'by' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::ADMIN_ADDED_NEW_USER->value,
                new DateTime(),
                $eventMetaData
            );

            return $this->redirectToRoute('admin_dashboard_admins');
        }

        return $this->render('dashboard/actions/add.html.twig', [
            'form' => $form->createView(),
            'userAddDTO' => $userAddDTO,
            'data' => $data,
            'current_user' => $currentUser,
            'context' => FirewallType::DASHBOARD->value,
            'isEditingSelf' => false,
        ]);
    }

    /**
     * Render a confirmation password form
     */
    /**
     * @param string $type Type of action
     */
    #[Route('/dashboard/confirm/{type}', name: 'admin_dashboard_confirm_reset')]
    #[IsGranted(UserAuthenticationVoter::USERS_MANAGEMENT_WRITE)]
    public function confirmReset(string $type): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        return $this->render('dashboard/actions/confirm.html.twig', [
            'data' => $data,
            'type' => $type,
            'user' => $currentUser,
        ]);
    }

    #[Route('/dashboard/admin/addPermissions/{id:user<\d+>}', name: 'admin_dashboard_add_admin_permissions')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function addPermissions(Request $request, User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($user->getId() === $currentUser->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (
            $user->getId() !== $currentUser->getId() &&
            !$this->isGranted(UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE)
        ) {
            throw $this->createAccessDeniedException();
        }

        $user->setRoles([AdminRoleType::ROLE_ADMIN->value]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $eventMetaData = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => PlatformMode::LIVE->value,
            'giveAdminPermissionsTo' => $user->getUuid(),
            'by' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::ADMIN_ADDED_PERMISSIONS->value,
            new DateTime(),
            $eventMetaData
        );

        return $this->redirect($request->headers->get('Referer'));
    }

    #[Route('/dashboard/admin/removePermissions/{id:user<\d+>}', name: 'admin_dashboard_remove_user_permissions')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function removePermissions(Request $request, User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($user->getId() === $currentUser->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (
            $user->getId() !== $currentUser->getId() && !$this->isGranted(
                UserAuthenticationVoter::ADMIN_MANAGEMENT_WRITE
            )
        ) {
            throw $this->createAccessDeniedException();
        }

        $user->setRoles(["ROLE_USER"]);
        $user->setPermissions([]);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $eventMetaData = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'platform' => PlatformMode::LIVE->value,
            'removeAdminPermissionsTo' => $user->getUuid(),
            'by' => $currentUser->getUuid(),
        ];

        $this->eventActions->saveEvent(
            $user,
            AnalyticalEventType::ADMIN_REMOVED_PERMISSIONS->value,
            new DateTime(),
            $eventMetaData
        );

        return $this->redirect($request->headers->get('Referer'));
    }
}