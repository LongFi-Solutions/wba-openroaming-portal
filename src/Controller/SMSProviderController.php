<?php

namespace App\Controller;

use App\DTO\SMSProviderDTO;
use App\Entity\Setting;
use App\Entity\SMSProvider;
use App\Entity\SMSProviderParam;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingName;
use App\Form\SMSProviderType;
use App\Repository\SettingRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(UserAuthenticationVoter::SMS_CONFIG_READ)]
class SMSProviderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly GetSettings $getSettings,
        private readonly SettingRepository $settingRepository,
        private readonly EventActions $eventActions,
    ) {
    }

    #[Route('/dashboard/settings/sms/providers', name: 'admin_dashboard_settings_sms_providers', methods: ['GET'])]
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

    #[Route(
        '/dashboard/settings/sms/providers/new',
        name: 'admin_dashboard_settings_sms_providers_new',
        methods: ['GET', 'POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function new(Request $request): Response
    {
        $dto = new SMSProviderDTO();
        $form = $this->createForm(SMSProviderType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();

            $provider = $this->createProviderFromDto($dto);
            $this->entityManager->flush();

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SMS_PROVIDER_CREATED->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::OLD_DATA->value => null,
                    EventMetadataKeysType::NEW_DATA->value => $provider->getName(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('SMSProviderSavedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        return $this->render('dashboard/shared/settings_actions/sms_providers/form.html.twig', [
            'form' => $form->createView(),
            'provider' => null,
            'data' => $this->getSettings->getSettings(),
        ]);
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/edit',
        name: 'admin_dashboard_settings_sms_providers_edit',
        methods: ['GET', 'POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function edit(SMSProvider $provider, Request $request): Response
    {
        $dto = SMSProviderDTO::fromEntity($provider);
        $form = $this->createForm(SMSProviderType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();

            // Capture before mutating, same reasoning as delete() — the entity's
            // old state is gone once updateProviderFromDto() overwrites it
            $previousName = $provider->getName();
            $previousTestMode = $provider->isTestMode();

            $this->updateProviderFromDto($provider, $dto);
            $this->entityManager->flush();

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SMS_PROVIDER_UPDATED->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::OLD_DATA->value => sprintf(
                        '%s (test mode: %s)',
                        $previousName,
                        $previousTestMode ? 'yes' : 'no'
                    ),
                    EventMetadataKeysType::NEW_DATA->value => sprintf(
                        '%s (test mode: %s)',
                        $provider->getName(),
                        $provider->isTestMode() ? 'yes' : 'no'
                    ),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('SMSProviderSavedSuccessfully', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        return $this->render('dashboard/shared/settings_actions/sms_providers/form.html.twig', [
            'form' => $form->createView(),
            'provider' => $provider,
            'data' => $this->getSettings->getSettings(),
        ]);
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/activate',
        name: 'admin_dashboard_settings_sms_providers_activate',
        methods: ['POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function activate(SMSProvider $provider, Request $request): Response
    {
        if (
            !$this->isCsrfTokenValid(
                'sms-provider-activate-' . $provider->getId(),
                $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $setting = $this->settingRepository->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value]);
        $previousActiveProviderName = $setting?->getValue();

        if ($setting === null) {
            $setting = new Setting();
            $setting->setName(SettingName::SMS_ACTIVE_PROVIDER->value);
            $this->entityManager->persist($setting);
        }

        $setting->setValue($provider->getName());
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::SMS_PROVIDER_ACTIVATED->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::OLD_DATA->value => $previousActiveProviderName,
                EventMetadataKeysType::NEW_DATA->value => $provider->getName(),
            ]
        );

        $this->addFlash(
            'success',
            $this->translator->trans('SMSProviderActivatedSuccessfully', [], 'controllers')
        );

        return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/deactivate',
        name: 'admin_dashboard_settings_sms_providers_deactivate',
        methods: ['POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function deactivate(SMSProvider $provider, Request $request): Response
    {
        if (
            !$this->isCsrfTokenValid(
                'sms-provider-deactivate-' . $provider->getId(),
                $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $setting = $this->settingRepository->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value]);
        $activeProviderName = $setting?->getValue();

        // Only the provider that's actually active can be deactivated — guards against
        // a stale page deactivating whatever happens to be active by the time this runs.
        if ($setting === null || $activeProviderName !== $provider->getName()) {
            $this->addFlash(
                'error',
                $this->translator->trans('ProviderIsNotActive', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        $setting->setValue(null);
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::SMS_PROVIDER_DEACTIVATED->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::OLD_DATA->value => $provider->getName(),
                EventMetadataKeysType::NEW_DATA->value => null,
            ]
        );

        $this->addFlash(
            'success',
            $this->translator->trans('SMSProviderDeactivatedSuccessfully', [], 'controllers')
        );

        return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
    }

    #[Route(
        '/dashboard/settings/sms/providers/{id}/delete',
        name: 'admin_dashboard_settings_sms_providers_delete',
        methods: ['POST']
    )]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function delete(SMSProvider $provider, Request $request): Response
    {
        if (
            !$this->isCsrfTokenValid(
                'sms-provider-delete-' . $provider->getId(),
                $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $activeProviderName = $this->settingRepository
            ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value])
            ?->getValue();

        if ($activeProviderName !== null && $activeProviderName === $provider->getName()) {
            $this->addFlash(
                'error',
                $this->translator->trans('cannotDeleteActiveSMSProvider', [], 'controllers')
            );

            return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
        }

        // Capture identifying info before removal, since the entity is gone from the DB after flush
        $deletedProviderName = $provider->getName();

        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::SMS_PROVIDER_DELETED->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::OLD_DATA->value => $deletedProviderName,
                EventMetadataKeysType::NEW_DATA->value => null,
            ]
        );

        $this->addFlash(
            'success',
            $this->translator->trans('SMSProviderDeletedSuccessfully', [], 'controllers')
        );

        return $this->redirectToRoute('admin_dashboard_settings_sms_providers');
    }

    /**
     * Builds and persists a brand-new SMSProvider (plus all its params) from the DTO.
     * No reconciliation needed here — every param on the DTO is necessarily a new row.
     */
    private function createProviderFromDto(SMSProviderDTO $dto): SMSProvider
    {
        $now = new DateTimeImmutable();

        $provider = new SMSProvider();
        $provider->setCreatedAt($now);
        $provider->setUpdatedAt($now);
        $provider->setName((string) $dto->name);
        $provider->setSMSProviderType($dto->smsProviderType);
        $provider->setTestMode($dto->testMode);
        $this->entityManager->persist($provider);

        foreach ($dto->params as $paramDto) {
            $param = new SMSProviderParam();
            $param->setCreatedAt($now);
            $param->setUpdatedAt($now);
            $param->setType($paramDto->type);
            $param->setParamType((string) $paramDto->paramType);
            $param->setValue((string) $paramDto->value);
            $provider->addSmsProviderParam($param);
            $this->entityManager->persist($param);
        }

        return $provider;
    }

    /**
     * Updates an existing SMSProvider in place from the DTO, reconciling its param
     * collection: rows still present get updated, new rows get created, and rows
     * removed from the form get deleted.
     */
    private function updateProviderFromDto(SMSProvider $provider, SMSProviderDTO $dto): void
    {
        $now = new DateTimeImmutable();

        $provider->setName((string) $dto->name);
        $provider->setSMSProviderType($dto->smsProviderType);
        $provider->setTestMode($dto->testMode);
        $provider->setUpdatedAt($now);

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

            $param->setType($paramDto->type);
            $param->setParamType((string) $paramDto->paramType);
            $param->setValue((string) $paramDto->value);
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
