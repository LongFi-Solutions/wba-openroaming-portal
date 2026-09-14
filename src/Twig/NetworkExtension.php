<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Network;
use App\Repository\AccessPointRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NetworkExtension
{
    public function __construct(
        private readonly AccessPointRepository $accessPointRepository
    ) {
    }

    #[\Twig\Attribute\AsTwigFunction(name: 'network_access_point_count')]
    public function getAccessPointCount(Network $network): int
    {
        return $this->accessPointRepository->count(['network' => $network]);
    }
}
