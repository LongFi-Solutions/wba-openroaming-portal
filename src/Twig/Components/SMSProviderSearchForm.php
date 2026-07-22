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
use Symfony\UX\LiveComponent\Attribute\LiveArg;
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

    #[LiveProp(writable: true)]
    public string $sort = 'createdAt';

    #[LiveProp(writable: true)]
    public string $order = 'desc';

    /** @var SMSProvider[]|null */
    private ?array $cachedFilteredProviders = null;

    /** @var SMSProvider[]|null */
    private ?array $cachedPageProviders = null;

    private ?SMSProvider $cachedActiveProvider = null;
    private bool $activeProviderResolved = false;

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
        return max(1, (int) ceil($this->getTotalProviders() / $this->count));
    }

    #[ExposeInTemplate]
    public function getActiveProvider(): ?SMSProvider
    {
        if (!$this->activeProviderResolved) {
            $this->activeProviderResolved = true;

            $activeName = $this->settingRepository
                ->findOneBy(['name' => SettingName::SMS_ACTIVE_PROVIDER->value])
                ?->getValue();

            $this->cachedActiveProvider = $activeName !== null
                ? $this->smsProviderRepository->findOneBy(['name' => $activeName])
                : null;
        }

        return $this->cachedActiveProvider;
    }

    #[ExposeInTemplate]
    public function getActiveProviderName(): ?string
    {
        return $this->getActiveProvider()?->getName();
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

    #[LiveAction]
    public function changeSort(#[LiveArg] string $field): void
    {
        if ($this->sort === $field) {
            $this->order = $this->order === 'desc' ? 'asc' : 'desc';
        } else {
            $this->sort = $field;
            $this->order = 'desc';
        }
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
                $providers = array_values(array_filter(
                    $providers,
                    static fn (SMSProvider $provider): bool =>
                        str_contains(mb_strtolower($provider->getName() ?? ''), $needle)
                ));
            }

            usort($providers, function (SMSProvider $a, SMSProvider $b): int {
                $result = match ($this->sort) {
                    'name' => strcmp($a->getName() ?? '', $b->getName() ?? ''),
                    default => $a->getCreatedAt() <=> $b->getCreatedAt(),
                };

                return $this->order === 'asc' ? $result : -$result;
            });

            $this->cachedFilteredProviders = $providers;
        }

        return $this->cachedFilteredProviders;
    }
}
