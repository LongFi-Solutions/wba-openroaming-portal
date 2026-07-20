<?php

namespace App\Twig\Components;

use App\Entity\Setting;
use App\Entity\SMSProvider;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Repository\SMSProviderRepository;
use App\Security\Voter\UserAuthenticationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
class SMSProviderSearchForm
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    /** @var SMSProvider[]|null */
    private ?array $cachedProviders = null;

    public function __construct(
        private readonly SMSProviderRepository $smsProviderRepository,
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return SMSProvider[]
     */
    #[ExposeInTemplate]
    public function getProviders(): array
    {
        if ($this->cachedProviders === null) {
            $providers = $this->smsProviderRepository->findAll();

            if ($this->query !== '') {
                $needle = mb_strtolower($this->query);
                $providers = array_values(
                    array_filter(
                        $providers,
                        static fn(SMSProvider $provider): bool => str_contains(
                                mb_strtolower($provider->getName() ?? ''),
                                $needle
                            )
                            || str_contains(mb_strtolower($provider->getAddress() ?? ''), $needle)
                    )
                );
            }

            $this->cachedProviders = $providers;
        }

        return $this->cachedProviders;
    }

    #[ExposeInTemplate]
    public function getActiveProviderName(): ?string
    {
        return $this->settingRepository
            ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value])
            ?->getValue();
    }

    #[ExposeInTemplate]
    public function getCanWrite(): bool
    {
        return $this->security->isGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE);
    }

    #[LiveAction]
    public function activate(#[LiveArg] int $id): void
    {
        if (!$this->getCanWrite()) {
            throw new AccessDeniedException();
        }

        $provider = $this->smsProviderRepository->find($id);
        if (!$provider instanceof SMSProvider) {
            return;
        }

        $setting = $this->settingRepository->findOneBy([
            'name' => SettingName::SMS_ACTIVE_PROVIDER->value
        ]);

        if ($setting === null) {
            $setting = new Setting();
            $setting->setName(SettingName::SMS_ACTIVE_PROVIDER->value);
            $this->entityManager->persist($setting);
        }

        $setting->setValue($provider->getName());
        $this->entityManager->flush();

        $this->cachedProviders = null;
        $this->addFlash('success', $this->translator->trans('SMSProviderActivatedSuccessfully', [], 'controllers'));
    }

    #[LiveAction]
    public function delete(#[LiveArg] int $id): void
    {
        if (!$this->getCanWrite()) {
            throw new AccessDeniedException();
        }

        $provider = $this->smsProviderRepository->find($id);
        if (!$provider instanceof SMSProvider) {
            return;
        }

        if ($this->getActiveProviderName() === $provider->getName()) {
            $this->addFlash('error', $this->translator->trans('CannotDeleteActiveSMSProvider', [], 'controllers'));

            return;
        }

        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        $this->cachedProviders = null;
        $this->addFlash('success', $this->translator->trans('SMSProviderDeletedSuccessfully', [], 'controllers'));
    }
}
