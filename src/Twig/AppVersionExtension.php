<?php

namespace App\Twig;

use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppVersionExtension extends AbstractExtension
{
    private readonly string $projectDir;
    private readonly string $environment;

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
        $this->environment = $kernel->getEnvironment();
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_version', $this->getAppVersion(...)),
            new TwigFunction('app_branch', $this->getAppBranch(...)),
        ];
    }

    public function getAppVersion(): ?string
    {
        $composerJsonPath = $this->projectDir . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new RuntimeException('Unable to fetch version');
        }

        $composerJsonContent = file_get_contents($composerJsonPath);
        if ($composerJsonContent === false) {
            throw new RuntimeException('Unable to read composer.json');
        }

        /** @var array<string, mixed> $composerJsonDecoded */
        $composerJsonDecoded = json_decode($composerJsonContent, true, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Unable to decode composer.json: ' . json_last_error_msg());
        }

        return $composerJsonDecoded['version'] ?? null;
    }

    public function getAppBranch(): ?string
    {
        if ($this->environment === 'prod') {
            return null;
        }

        $headFile = $this->projectDir . '/.git/HEAD';

        if (!is_file($headFile)) {
            return null;
        }

        $head = trim((string)file_get_contents($headFile));

        if (str_starts_with($head, 'ref:')) {
            return trim(str_replace('ref: refs/heads/', '', $head));
        }

        // detached HEAD — show short commit hash
        return substr($head, 0, 7);
    }
}
