<?php

namespace App\Twig\Components;

use App\Entity\SMSProvider;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use App\Repository\SMSProviderRepository;
use App\Security\Voter\UserAuthenticationVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
class SMSProviderSearchForm
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $count = 7;

    /** @var SMSProvider[]|null */
    private ?array $cachedFilteredProviders = null;

    /** @var SMSProvider[]|null */
    private ?array $cachedPageProviders = null;

    public function __construct(
        private readonly SMSProviderRepository $smsProviderRepository,
        private readonly SettingRepository $settingRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return SMSProvider[]
     */
    #[ExposeInTemplate]
    public function getProviders(): array
    {
        if ($this->cachedPageProviders === null) {
            $offset = ($this->page - 1) * $this->count;
            $this->cachedPageProviders = array_slice($this->getFilteredProviders(), $offset, $this->count);
        }

        return $this->cachedPageProviders;
    }

    #[ExposeInTemplate]
    public function getTotalProviders(): int
    {
        return count($this->getFilteredProviders());
    }

    #[ExposeInTemplate]
    public function getTotalPages(): int
    {
        return max(1, (int)ceil($this->getTotalProviders() / $this->count));
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
    public function prevPage(): void
    {
        $this->page--;
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->page++;
    }

    /**
     * @return SMSProvider[]
     */
    private function getFilteredProviders(): array
    {
        if ($this->cachedFilteredProviders === null) {
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

            $this->cachedFilteredProviders = $providers;
        }

        return $this->cachedFilteredProviders;
    }
}
