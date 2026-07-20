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

#[Route('/dashboard/settings/sms/providers')]
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

    #[Route('/{id}/activate', name: 'admin_dashboard_settings_sms_providers_activate', methods: ['POST'])]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function activate(SMSProvider $provider, Request $request): Response
    {
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

    #[Route('/{id}/delete', name: 'admin_dashboard_settings_sms_providers_delete', methods: ['POST'])]
    #[IsGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE)]
    public function delete(SMSProvider $provider, Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $activeProviderName = $this->settingRepository
            ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value])
            ?->getValue();

        if ($activeProviderName !== null && $activeProviderName === $provider->getName()) {
            $this->addFlash(
                'error',
                $this->translator->trans('CannotDeleteActiveSMSProvider', [], 'controllers')
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

        $provider->setName((string) $dto->name);
        $provider->setAddress((string) $dto->address);
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
