<?php

namespace App\Controller;

use App\DTO\SMSProviderDTO;
use App\Entity\SMSProvider;
use App\Entity\SMSProviderParam;
use App\Entity\User;
use App\Form\SMSProviderType;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\GetSettings;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/dashboard/settings/sms/providers')]
#[IsGranted(UserAuthenticationVoter::SMS_CONFIG_READ)]
class SMSProviderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly GetSettings $getSettings,
    ) {
    }

    #[Route('', name: 'admin_dashboard_settings_sms_providers', methods: ['GET'])]
    public function index(): Response
    {
        $data = $this->getSettings->getSettings();
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        return $this->render(
            'dashboard/shared/settings_actions/sms_providers/index.html.twig',
            [
                'data' => $data,
                'currentUser' => $currentUser,
            ]
        );
    }

    #[Route('/new', name: 'admin_dashboard_settings_sms_providers_new', methods: ['GET', 'POST'])]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function new(Request $request): Response
    {
        $dto = new SMSProviderDTO();

        return $this->handleForm($request, $dto, null);
    }

    #[Route('/{id}/edit', name: 'admin_dashboard_settings_sms_providers_edit', methods: ['GET', 'POST'])]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function edit(SMSProvider $provider, Request $request): Response
    {
        return $this->handleForm($request, SMSProviderDTO::fromEntity($provider), $provider);
    }

    private function handleForm(Request $request, SMSProviderDTO $dto, ?SMSProvider $provider): Response
    {
        $form = $this->createForm(SMSProviderType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyDtoToEntity($dto, $provider);
            $this->entityManager->flush();

            $this->addFlash(
                'success',
                $this->translator->trans('SMSProviderSavedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        return $this->render('dashboard/shared/settings_actions/sms_providers/form.html.twig', [
            'form' => $form->createView(),
            'provider' => $provider,
        ]);
    }

    private function applyDtoToEntity(SMSProviderDTO $dto, ?SMSProvider $provider): void
    {
        $now = new DateTimeImmutable();

        if ($provider === null) {
            $provider = new SMSProvider();
            $provider->setCreatedAt($now);
            $this->entityManager->persist($provider);
        }

        $provider->setName((string)$dto->name);
        $provider->setAddress((string)$dto->address);
        $provider->setUpdatedAt($now);

        // Reconcile the param collection against the DTO: update rows that still exist,
        // add new ones, and delete any that were removed in the form.
        $existingParams = [];
        foreach ($provider->getSmsProviderParams() as $existingParam) {
            $existingParams[$existingParam->getId()] = $existingParam;
        }

        $keptIds = [];
        foreach ($dto->params as $paramDto) {
            if ($paramDto->id !== null && isset($existingParams[$paramDto->id])) {
                $param = $existingParams[$paramDto->id];
            } else {
                $param = new SMSProviderParam();
                $param->setCreatedAt($now);
                $provider->addSmsProviderParam($param);
                $this->entityManager->persist($param);
            }

            $param->setParamType((string)$paramDto->paramType);
            $param->setValue((string)$paramDto->value);
            $param->setUpdatedAt($now);

            if ($param->getId() !== null) {
                $keptIds[] = $param->getId();
            }
        }

        foreach ($existingParams as $id => $existingParam) {
            if (!in_array($id, $keptIds, true)) {
                $provider->removeSmsProviderParam($existingParam);
                $this->entityManager->remove($existingParam);
            }
        }
    }
}
