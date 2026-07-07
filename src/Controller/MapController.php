<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AccessPointDTO;
use App\DTO\NetworkDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Enum\AdminPermissionsType;
use App\Form\CreateAccessPointType;
use App\Form\CreateNetworkType;
use App\Repository\AccessPointRepository;
use App\Repository\NetworkRepository;
use App\Service\GeoLocationResolver;
use App\Service\GetSettings;
use App\Service\Map\NetworkGeometryMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

class MapController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly NetworkRepository $networkRepository,
        private readonly AccessPointRepository $accessPointRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly GeoLocationResolver $geoLocationResolver,
        private readonly NetworkGeometryMapper $networkGeometryMapper,
    ) {
    }

    #[Route('/map', name: 'app_map')]
    public function index(Request $request): Response
    {
        $data = $this->getSettings->getSettings();

        $hasLocationConsent = $this->hasLocationConsent($request);

        $centerLat = null;
        $centerLng = null;

        if ($hasLocationConsent) {
            $ip = $request->getClientIp();

            if ($ip !== null) {
                $coordinates = $this->geoLocationResolver->getCoordinatesFromIp($ip);

                if ($coordinates !== null) {
                    [$centerLat, $centerLng] = $coordinates;
                }
            }
        }

        // Default fallback center
        $defaultLat = 37.7412;
        $defaultLng = -25.6756;

        $map = new Map()
            ->center(new Point(
                (float)($centerLat ?? $defaultLat),
                (float)($centerLng ?? $defaultLng)
            ))
            ->zoom($centerLat !== null ? 14 : 6);

        // Polygons are no longer loaded here. The initial viewport bounds
        // don't exist server-side — they're only known once Leaflet mounts
        // client-side. The Stimulus controller fetches them from
        // /map/polygons on connect and on every `moveend`.

        $needsBrowserGeolocation = $hasLocationConsent && $centerLat === null;

        return $this->render('landing/map/index.html.twig', [
            'data' => $data,
            'map' => $map,
            'needsBrowserGeolocation' => $needsBrowserGeolocation,
        ]);
    }

    #[Route('/map/polygons', name: 'app_map_polygons', methods: ['GET'])]
    public function polygons(Request $request): Response
    {
        $minLat = $request->query->get('minLat');
        $minLng = $request->query->get('minLng');
        $maxLat = $request->query->get('maxLat');
        $maxLng = $request->query->get('maxLng');

        if ($minLat === null || $minLng === null || $maxLat === null || $maxLng === null) {
            return $this->json(['error' => 'Missing bbox parameters'], Response::HTTP_BAD_REQUEST);
        }

        $networks = $this->networkRepository->findIntersectingBbox(
            (float)$minLat,
            (float)$minLng,
            (float)$maxLat,
            (float)$maxLng,
        );

        $features = array_map(
            static fn(Network $network): array => [
                'id' => $network->getId(),
                'name' => $network->getName(),
                'geometry' => $network->getGeometry(),
            ],
            $networks
        );

        return $this->json($features);
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
        $map = new Map()
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

    #[Route('dashboard/map/network/create', name: 'admin_dashboard_map_network_create')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function createNetwork(Request $request): ?Response
    {
        $data = $this->getSettings->getSettings();
        $networkDTO = new NetworkDTO();
        $form = $this->createForm(CreateNetworkType::class, $networkDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $network = new Network();
            $networkDTO->updateEntity($network);
            $network->setCreatedAt(new DateTimeImmutable());
            $network->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($network);
            $this->entityManager->flush();
            $this->addFlash(
                'success',
                $this->translator->trans(
                    'successNetworkCreate',
                    ['%network%' => $network->getName()],
                    'controllers'
                )
            );
            return $this->redirectToRoute('admin_dashboard_map');
        }

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        $centerLat = $lat ?? 37.7412;
        $centerLng = $lng ?? -25.6756;

        $map = new Map()
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom(13);
        return $this->render('dashboard/shared/settings_actions/map/create.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'networkDTO' => $networkDTO,
            'network' => null,

        ]);
    }

    #[Route(
        'dashboard/map/network/delete/{id:network<\d+>}',
        name: 'admin_dashboard_map_network_delete'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function deleteNetwork(Network $network): Response
    {
        $this->entityManager->remove($network);
        $this->entityManager->flush();
        $this->addFlash(
            'success',
            $this->translator->trans(
                'successNetworkDelete',
                ['%network%' => $network->getName()],
                'controllers'
            )
        );
        return $this->redirectToRoute('admin_dashboard_map');
    }

    #[Route('dashboard/map/network/edit/{id:network<\d+>}', name: 'admin_dashboard_map_network_edit')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function editNetwork(Network $network, Request $request): Response
    {
        $data = $this->getSettings->getSettings();
        $networkDTO = new NetworkDTO();
        $networkDTO->name = $network->getName();
        $networkDTO->description = $network->getDescription();

        $networkDTO->accessPointsFromDatabase = $this->accessPointRepository->findBy(['network' => $network]);
        if ($network->getGeometry() !== null) {
            $networkDTO->geometryJson = json_encode($network->getGeometry(), JSON_THROW_ON_ERROR);
        }
        $form = $this->createForm(CreateNetworkType::class, $networkDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $networkDTO->updateEntity($network);
            if (!in_array($networkDTO->geometryJson, [null, '', '0'], true)) {
                $network->setGeometry(json_decode($networkDTO->geometryJson, true));
            } else {
                $network->setGeometry(null);
            }

            $network->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($network);
            $this->entityManager->flush();
            $this->addFlash(
                'success',
                $this->translator->trans(
                    'successNetworkEdit',
                    ['%network%' => $network->getName()],
                    'controllers'
                )
            );
            return $this->redirectToRoute('admin_dashboard_map');
        }

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $centerLat = $lat ?? 37.7412;
        $centerLng = $lng ?? -25.6756;

        $map = new Map()
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom(13);

        $accessPoints = $this->entityManager->getRepository(AccessPoint::class)->findBy(['network' => $network]);

        foreach ($accessPoints as $ap) {
            $location = $ap->getLocation();

            if ($location && isset($location['coordinates']) && is_array($location['coordinates'])) {
                $lng = $location['coordinates'][0] ?? null;
                $lat = $location['coordinates'][1] ?? null;

                if ($lat !== null && $lng !== null) {
                    $map->addMarker(new Marker(
                        position: new Point((float)$lat, (float)$lng),
                        title: $ap->getName() ?? 'Access Point'
                    ));
                }
            }
        }

        return $this->render('dashboard/shared/settings_actions/map/create.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'networkDTO' => $networkDTO,
            'network' => $network,
        ]);
    }

    #[Route(
        'dashboard/map/network/{id:network<\d+>}/accessPoints',
        name: 'admin_dashboard_map_network_accessPoints'
    )]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function networkAccessPoints(Network $network): Response
    {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions/map/access_points.html.twig', [
            'data' => $data,
            'network' => $network,
        ]);
    }

    #[Route(
        'dashboard/map/network/{id:network<\d+>}/accessPoints/create',
        name: 'admin_dashboard_map_accessPoint_create'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function networkAccessPointsCreate(Network $network, Request $request): Response
    {
        $data = $this->getSettings->getSettings();
        $accessPointDTO = new AccessPointDTO();
        $accessPointDTO->network = $network;
        if ($accessPointDTO->latitude !== null && $accessPointDTO->longitude !== null) {
            $centerLat = $accessPointDTO->latitude;
            $centerLng = $accessPointDTO->longitude;
            $zoom = 16;
        } else {
            $centerLat = $request->query->get('lat') ?? 37.7412;
            $centerLng = $request->query->get('lng') ?? -25.6756;
            $zoom = 13;
        }

        $map = new Map()
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom($zoom);
        $form = $this->createForm(CreateAccessPointType::class, $accessPointDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $accessPoint = new AccessPoint();
            $accessPointDTO->updateEntity($accessPoint);
            $accessPoint->setCreatedAt(new DateTimeImmutable());
            $accessPoint->setUpdatedAt(new DateTimeImmutable());
            $network->addAccessPoint($accessPoint);
            $this->entityManager->persist($accessPoint);
            $this->entityManager->persist($network);
            $this->entityManager->flush();
            $this->addFlash(
                'success',
                $this->translator->trans(
                    'successAccessPointCreate',
                    ['%accessPoint%' => $accessPoint->getSsid()],
                    'controllers'
                )
            );
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                [
                    'id' => $network->getId(),
                ]
            );
        }
        return $this->render('dashboard/shared/settings_actions/map/access_point/create.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'network' => $network,
            'accessPointDTO' => $accessPointDTO,
            'accessPoint' => null,

        ]);
    }

    #[Route(
        'dashboard/map/network/{network_id<\d+>}/accessPoints/{ap_id<\d+>}/edit',
        name: 'admin_dashboard_map_accessPoint_edit'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function networkAccessPointsEdit(
        #[MapEntity(id: 'ap_id')] AccessPoint $accessPoint,
        #[MapEntity(id: 'network_id')] Network $network,
        Request $request
    ): Response {
        $data = $this->getSettings->getSettings();
        $accessPointDTO = AccessPointDTO::createFromEntity($accessPoint);
        if ($accessPointDTO->latitude !== null && $accessPointDTO->longitude !== null) {
            $centerLat = $accessPointDTO->latitude;
            $centerLng = $accessPointDTO->longitude;
            $zoom = 16;
        } else {
            $centerLat = $request->query->get('lat') ?? 37.7412;
            $centerLng = $request->query->get('lng') ?? -25.6756;
            $zoom = 13;
        }

        $map = new Map()
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom($zoom);

        $form = $this->createForm(CreateAccessPointType::class, $accessPointDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $accessPointDTO->updateEntity($accessPoint);
            $accessPoint->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->persist($accessPoint);
            $this->entityManager->flush();
            $this->addFlash(
                'success',
                $this->translator->trans(
                    'successAccessPointEdit',
                    ['%accessPoint%' => $accessPoint->getSsid()],
                    'controllers'
                )
            );
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                [
                    'id' => $network->getId(),
                ]
            );
        }
        return $this->render('dashboard/shared/settings_actions/map/access_point/create.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'accessPointDTO' => $accessPointDTO,
            'network' => $network,
            'accessPoint' => $accessPoint,

        ]);
    }

    #[Route(
        'dashboard/map/network/{network_id<\d+>}/accessPoints/{ap_id<\d+>}/delete',
        name: 'admin_dashboard_map_accessPoint_delete'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function networkAccessPointsDelete(
        #[MapEntity(id: 'ap_id')] AccessPoint $accessPoint,
        #[MapEntity(id: 'network_id')] Network $network,
    ): Response {
        $this->entityManager->remove($accessPoint);
        $this->entityManager->flush();
        $this->addFlash(
            'success',
            $this->translator->trans(
                'successAccessPointDelete',
                ['%accessPoint%' => $accessPoint->getSsid()],
                'controllers'
            )
        );
        return $this->redirectToRoute(
            'admin_dashboard_map_network_accessPoints',
            [
                'id' => $network->getId(),
            ]
        );
    }

    /**
     * Location lookups (IP-based or browser-based) are only allowed once the user
     * has accepted all cookies, or explicitly enabled the "rememberMe" scope
     * in their saved cookie preferences.
     */
    private function hasLocationConsent(Request $request): bool
    {
        if ($request->cookies->get('cookies_accepted') === 'true') {
            return true;
        }

        $preferencesCookie = $request->cookies->get('cookie_preferences');

        if ($preferencesCookie === null) {
            return false;
        }

        try {
            $preferences = json_decode($preferencesCookie, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return ($preferences['rememberMe'] ?? false) === true;
    }
}
