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
use App\Service\GetSettings;
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
    ) {
    }

    #[Route('/map', name: 'app_map')]
    public function index(Request $request): Response
    {
        $data = $this->getSettings->getSettings();

        $hasLocationConsent = $this->hasLocationConsent($request);

        $centerLat = $request->cookies->get('user_lat');
        $centerLng = $request->cookies->get('user_lng');

        // Default fallback center
        $defaultLat = 37.7412;
        $defaultLng = -25.6756;

        $map = new Map()
            ->center(new Point(
                (float)($centerLat ?? $defaultLat),
                (float)($centerLng ?? $defaultLng)
            ))
            ->zoom($centerLat !== null ? 14 : 6);

        $needsBrowserGeolocation = $hasLocationConsent && $centerLat === null;

        return $this->render('landing/map/index.html.twig', [
            'data' => $data,
            'map' => $map,
            'needsBrowserGeolocation' => $needsBrowserGeolocation,
        ]);
    }

    /**
     * Public map: networks only, never access points.
     *
     * @throws JsonException
     */
    #[Route('/map/polygons', name: 'app_map_polygons', methods: ['GET'])]
    public function polygons(Request $request): Response
    {
        $bbox = $this->parseBboxOrFail($request);
        if ($bbox instanceof Response) {
            return $bbox;
        }
        [$minLat, $minLng, $maxLat, $maxLng] = $bbox;

        $networks = $this->networkRepository->findIntersectingBbox($minLat, $minLng, $maxLat, $maxLng);

        return $this->json([
            'networks' => $this->serializeNetworks($networks),
            'accessPoints' => [],
        ]);
    }

    #[Route('dashboard/map', name: 'admin_dashboard_map')]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function mapManagement(Request $request): Response
    {
        $lat = $request->cookies->get('user_lat');
        $lng = $request->cookies->get('user_lng');

        $centerLat = $lat ?? 37.7412;
        $centerLng = $lng ?? -25.6756;

        $data = $this->getSettings->getSettings();
        $map = new Map()
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom(13);

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'map' => $map,
            'data' => $data,
            'searchTerm' => null,
        ]);
    }

    /**
     * Dashboard map: networks + access points, both bbox-filtered.
     *
     * @throws JsonException
     */
    #[Route('dashboard/map/polygons', name: 'admin_dashboard_map_polygons', methods: ['GET'])]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function dashboardPolygons(Request $request): Response
    {
        $bbox = $this->parseBboxOrFail($request);
        if ($bbox instanceof Response) {
            return $bbox;
        }
        [$minLat, $minLng, $maxLat, $maxLng] = $bbox;

        $networks = $this->networkRepository->findIntersectingBbox($minLat, $minLng, $maxLat, $maxLng);
        $accessPoints = $this->accessPointRepository->findIntersectingBbox($minLat, $minLng, $maxLat, $maxLng);

        return $this->json([
            'networks' => $this->serializeNetworks($networks),
            'accessPoints' => array_map(
                static function (array $ap): array {
                    return [
                        'id' => $ap['id'],
                        'name' => $ap['name'] ?? $ap['ssid'],
                        'lat' => (float) $ap['lat'],
                        'lng' => (float) $ap['lng'],
                    ];
                },
                $accessPoints
            ),
        ]);
    }

    /**
     * @throws JsonException
     */
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
                $this->translator->trans('successNetworkCreate', ['%network%' => $network->getName()], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_map');
        }

        $lat = $request->cookies->get('user_lat');
        $lng = $request->cookies->get('user_lng');
        $centerLat = $lat ?? 37.7412;
        $centerLng = $lng ?? -25.6756;

        $map = new Map()
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom(13);
        return $this->render('dashboard/shared/settings_actions/map/manage_network.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'networkDTO' => $networkDTO,
            'network' => null,
        ]);
    }

    #[Route('dashboard/map/network/delete/{id:network<\d+>}', name: 'admin_dashboard_map_network_delete')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function deleteNetwork(Network $network): Response
    {
        $this->entityManager->remove($network);
        $this->entityManager->flush();
        $this->addFlash(
            'success',
            $this->translator->trans('successNetworkDelete', ['%network%' => $network->getName()], 'controllers')
        );
        return $this->redirectToRoute('admin_dashboard_map');
    }

    /**
     * @throws JsonException
     */
    #[Route('dashboard/map/network/edit/{id:network<\d+>}', name: 'admin_dashboard_map_network_edit')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function editNetwork(Network $network, Request $request): Response
    {
        $data = $this->getSettings->getSettings();
        $networkDTO = new NetworkDTO();
        $networkDTO->networkId = $network->getId();
        $networkDTO->name = $network->getName();
        $networkDTO->description = $network->getDescription();
        $networkDTO->geometryJson = $network->getGeometry();

        $form = $this->createForm(CreateNetworkType::class, $networkDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $networkDTO->updateEntity($network);
            $network->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($network);
            $this->entityManager->flush();
            $this->addFlash(
                'success',
                $this->translator->trans('successNetworkEdit', ['%network%' => $network->getName()], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_map');
        }

        $lat = $request->cookies->get('user_lat');
        $lng = $request->cookies->get('user_lng');
        $centerLat = $lat ?? 37.7412;
        $centerLng = $lng ?? -25.6756;

        $map = new Map()
            ->center(new Point((float)$centerLat, (float)$centerLng))
            ->zoom(13);

        $accessPoints = $this->entityManager->getRepository(AccessPoint::class)->findBy(['network' => $network]);

        foreach ($accessPoints as $ap) {
            $locationJson = $ap->getLocation();

            if ($locationJson !== null) {
                $location = json_decode($locationJson, true, 512, JSON_THROW_ON_ERROR);

                if (isset($location['coordinates']) && is_array($location['coordinates'])) {
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
        }

        return $this->render('dashboard/shared/settings_actions/map/manage_network.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'networkDTO' => $networkDTO,
            'network' => $network,
        ]);
    }

    #[Route('dashboard/map/network/{id:network<\d+>}/accessPoints', name: 'admin_dashboard_map_network_accessPoints')]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function networkAccessPoints(Network $network): Response
    {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions/map/access_points.html.twig', [
            'data' => $data,
            'network' => $network,
        ]);
    }

    #[Route('dashboard/map/network/{id:network<\d+>}/accessPoints/create', name: 'admin_dashboard_map_accessPoint_create')]
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
            return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
        }

        return $this->render('dashboard/shared/settings_actions/map/access_point/manage_access_points.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'network' => $network,
            'networkGeometry' => $network->getGeometry(),
            'accessPointDTO' => $accessPointDTO,
            'accessPoint' => null,
            'otherAccessPoints' => $this->serializeOtherAccessPoints($network, null),
        ]);
    }

    /**
     * @throws JsonException
     */
    #[Route('dashboard/map/network/{network_id<\d+>}/accessPoints/{ap_id<\d+>}/edit', name: 'admin_dashboard_map_accessPoint_edit')]
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
            $centerLat = $request->cookies->get('user_lat');
            $centerLng = $request->cookies->get('user_lng');
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
            return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
        }
        return $this->render('dashboard/shared/settings_actions/map/access_point/manage_access_points.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'accessPointDTO' => $accessPointDTO,
            'network' => $network,
            'accessPoint' => $accessPoint,
            'networkGeometry' => $network->getGeometry(),
            'otherAccessPoints' => $this->serializeOtherAccessPoints($network, $accessPoint),
        ]);
    }

    #[Route('dashboard/map/network/{network_id<\d+>}/accessPoints/{ap_id<\d+>}/delete', name: 'admin_dashboard_map_accessPoint_delete')]
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
        return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
    }

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

    private function parseBboxOrFail(Request $request): array|Response
    {
        $minLat = $request->query->get('minLat');
        $minLng = $request->query->get('minLng');
        $maxLat = $request->query->get('maxLat');
        $maxLng = $request->query->get('maxLng');

        if ($minLat === null || $minLng === null || $maxLat === null || $maxLng === null) {
            return $this->json(['error' => 'Missing bbox parameters'], Response::HTTP_BAD_REQUEST);
        }

        return [(float)$minLat, (float)$minLng, (float)$maxLat, (float)$maxLng];
    }

    /**
     * @param Network[] $networks
     * @return array<int, array{id: int, name: string, geometry: mixed}>
     * @throws JsonException
     */
    private function serializeNetworks(array $networks): array
    {
        return array_map(
            static fn(Network $network): array => [
                'id' => $network->getId(),
                'name' => $network->getName(),
                'geometry' => $network->getGeometry() !== null
                    ? json_decode($network->getGeometry(), true, 512, JSON_THROW_ON_ERROR)
                    : null,
            ],
            $networks
        );
    }

    /**
     * @return array<int, array{lat: float, lng: float, name: string}>
     */
    private function serializeOtherAccessPoints(Network $network, ?AccessPoint $excludeAccessPoint): array
    {
        $accessPoints = $this->accessPointRepository->findBy(['network' => $network]);

        return array_values(array_filter(array_map(
            static function (AccessPoint $ap) use ($excludeAccessPoint): ?array {
                if ($excludeAccessPoint !== null && $ap->getId() === $excludeAccessPoint->getId()) {
                    return null;
                }

                $location = $ap->getLocationData();
                if ($location === null) {
                    return null;
                }

                return [
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],
                    'name' => $ap->getName() ?? $ap->getSsid(),
                ];
            },
            $accessPoints
        )));
    }
}
