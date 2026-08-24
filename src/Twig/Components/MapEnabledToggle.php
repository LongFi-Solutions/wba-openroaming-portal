<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class MapEnabledToggle extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public bool $enabled = false;

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly SettingsService $settingsService,
        private readonly EventActions $eventActions,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function mount(): void
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $this->enabled = strtoupper((string)($data[SettingName::MAP_ENABLED->value]['value'] ?? 'OFF')) === 'ON';
    }

    #[LiveAction]
    public function toggle(): void
    {
        if (!$this->security->isGranted(UserAuthenticationVoter::MAP_WRITE)) {
            return;
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            return;
        }

        $oldValue = $this->enabled ? OperationMode::ON->value : OperationMode::OFF->value;
        $this->enabled = !$this->enabled;
        $newValue = $this->enabled ? OperationMode::ON->value : OperationMode::OFF->value;

        $this->settingsService->update(SettingName::MAP_ENABLED->value, $newValue);
        $this->settingsService->flush();

        $request = $this->requestStack->getCurrentRequest();

        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::SETTING_MAP_REQUEST->value,
            new DateTime(),
            [
                EventMetadataKeysType::IP->value => $request?->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request?->headers->get('User-Agent'),
                EventMetadataKeysType::CHANGESET->value => [
                    SettingName::MAP_ENABLED->value => [
                        EventMetadataKeysType::OLD_DATA->value => $oldValue,
                        EventMetadataKeysType::NEW_DATA->value => $newValue,
                    ],
                ],
            ]
        );
    }
}
