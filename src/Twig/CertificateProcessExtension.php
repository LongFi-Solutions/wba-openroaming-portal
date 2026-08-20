<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\CertificateSetupProcess;
use App\Service\CertificateProcessCheckerService;
use App\Enum\ProcessStatusType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CertificateProcessExtension
{
    public function __construct(
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService
    ) {
    }

    /**
     * Check if the current certificate process is aborted
     */
    #[\Twig\Attribute\AsTwigFunction(name: 'isCertificateAborted')]
    public function isCertificateAborted(): bool
    {
        $currentProcess = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$currentProcess instanceof CertificateSetupProcess) {
            return false;
        }

        return $currentProcess->getStatus() === ProcessStatusType::ABORTED;
    }

    /**
     * Check if the current certificate process is aborted OR invalid
     * Use this to block profile downloads
     */
    #[\Twig\Attribute\AsTwigFunction(name: 'isCertificateProcessBlocked')]
    public function isCertificateProcessBlocked(): bool
    {
        $currentProcess = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$currentProcess instanceof CertificateSetupProcess) {
            return false;
        }

        return in_array($currentProcess->getStatus(), [
            ProcessStatusType::ABORTED,
            ProcessStatusType::INVALID,
        ], strict: true);
    }
}
