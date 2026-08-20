<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\CertificateFileName;
use App\Service\CertificateCheckerService;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CertificateExpirationDetectorExtension
{
    public function __construct(
        private readonly CertificateCheckerService $certificateService
    ) {
    }

    /**
     * Check cert.pem and return a simple status tag:
     *  - 'expired' if expired
     *  - 'warning' if <= 30 days left
     *  - null if more than 30 days left or file missing
     */
    #[\Twig\Attribute\AsTwigFunction(name: 'certStatusTag')]
    public function getCertStatusTag(): ?int
    {
        try {
            $daysLeft = $this->certificateService->certificateLimitDate(
                '/' . CertificateFileName::CERT_PEM_FILE->value
            );

            if ($daysLeft === null) {
                return null; // certificate not found or unreadable
            }

            if ($daysLeft <= 0) {
                return 1;
            }

            if ($daysLeft <= 30) {
                return 0;
            }
        } catch (Exception) {
            return null;
        }

        return null;
    }
}
