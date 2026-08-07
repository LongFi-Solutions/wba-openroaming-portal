<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Network;
use App\Repository\AccessPointRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NetworkExtension extends AbstractExtension
{
    public function __construct(
        private readonly AccessPointRepository $accessPointRepository
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('network_access_point_count', $this->getAccessPointCount(...)),
        ];
    }

    public function getAccessPointCount(Network $network): int
    {
        return $this->accessPointRepository->count(['network' => $network]);
    }
}
