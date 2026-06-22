<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AdminPermissionsType;
use App\Repository\NetworkRepository;
use App\Service\GetSettings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Point;

class MapController extends AbstractController
{

    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly NetworkRepository $networkRepository,
    ){}
    #[Route('/map', name: 'app_map')]
    public function index(Request $request): Response
    {

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        $centerLat = $lat ?? 37.7412;
        $centerLng = $lng ?? -25.6756;

        $data = $this->getSettings->getSettings();
        $map = (new Map())
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom(13);

        //$mapWithPoints = $this->accessPointService->addAccessPoints($map);

        return $this->render('landing/map/index.html.twig', [
            'map' => $map,
            'data' => $data,
        ]);
    }

    #[Route('dashboard/map', name: 'admin_dashboard_map')]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function mapManagement(Request $request): Response
    {

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        $centerLat = $lat ?? 37.7412;
        $centerLng = $lng ?? -25.6756;

        $data = $this->getSettings->getSettings();
        $map = (new Map())
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom(13);

        //$mapWithPoints = $this->accessPointService->addAccessPoints($map);

        $networks = $this->networkRepository->findAll();

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'map' => $map,
            'data' => $data,
            'networks' => $networks,
            'allNetworks' => count($networks),
            'allActiveNetworks' => count($networks),
            'searchTerm' => null

        ]);
    }
}
