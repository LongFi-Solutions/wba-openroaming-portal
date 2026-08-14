<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\EEAUserDetector;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EEAUserExtension
{
    public function __construct(
        private readonly EEAUserDetector $eeaUserDetector
    ) {
    }

    /**
     * Check if the current user is from the EEA
     */
    #[\Twig\Attribute\AsTwigFunction(name: 'isEEAUser')]
    public function isEEAUser(): int
    {
        return $this->eeaUserDetector->isEEAUser();
    }
}
