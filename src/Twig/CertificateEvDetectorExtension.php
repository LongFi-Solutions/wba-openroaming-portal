<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\CertificateSetupProcess;
use App\Enum\ProcessStatusType;
use App\Service\CertificateProcessCheckerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CertificateEvDetectorExtension
{
    public function __construct(
        private readonly CertificateProcessCheckerService $certificateProcessCheckerService
    ) {
    }

    /**
     * Check if the current FreeRADIUS certificate is EV
     */
    #[\Twig\Attribute\AsTwigFunction(name: 'isFreeradiusCertEV')]
    public function isFreeradiusCertEV(): bool
    {
        $process = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$process instanceof CertificateSetupProcess) {
            return false;
        }

        return $process->isFreeradiusCertEV();
    }

    /**
     * Check if the latest certificate process has been marked as invalid
     */
    #[\Twig\Attribute\AsTwigFunction(name: 'isCertificateProcessInvalid')]
    public function isCertificateProcessInvalid(): bool
    {
        $process = $this->certificateProcessCheckerService->getCurrentProcess();

        if (!$process instanceof CertificateSetupProcess) {
            return false;
        }

        return $process->getStatus() === ProcessStatusType::INVALID;
    }
}
