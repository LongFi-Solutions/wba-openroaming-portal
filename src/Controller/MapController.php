<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AccessPointDTO;
use App\DTO\MapSettingsDTO;
use App\DTO\NetworkDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Entity\User;
use App\Enum\AdminPermissionsType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\OperationMode;
use App\Enum\SettingName;
use App\Form\CreateAccessPointType;
use App\Form\CreateNetworkType;
use App\Form\MapSettingsType;
use App\Repository\AccessPointRepository;
use App\Repository\NetworkRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
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
        private readonly SettingsService $settingsService,
        private readonly EventActions $eventActions,
    ) {
    }

    #[Route('/map', name: 'app_map')]
    public function index(): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $mapEnabled = $data[SettingName::MAP_ENABLED->value]['value'] ?? OperationMode::OFF->value;

        if ($mapEnabled === OperationMode::OFF->value) {
            $this->addFlash(
                'error',
                $this->translator->trans('coverageMapDisabled', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        // Default fallback center
        $defaultLat = (float)$data[SettingName::MAP_CENTER_LATITUDE->value]['value'];
        $defaultLng = (float)$data[SettingName::MAP_CENTER_LONGITUDE->value]['value'];

        $map = new Map()
            ->center(new Point($defaultLat, $defaultLng))
            ->zoom((int)$data[SettingName::MAP_CENTER_ZOOM->value]['value']);

        return $this->render('landing/map/index.html.twig', [
            'data' => $data,
            'map' => $map,
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
        ]);
    }

    #[Route('dashboard/map', name: 'admin_dashboard_map')]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function mapManagement(): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $centerLat = (float)$data[SettingName::MAP_CENTER_LATITUDE->value]['value'];
        $centerLng = (float)$data[SettingName::MAP_CENTER_LONGITUDE->value]['value'];

        $map = new Map()
            ->center(new Point($centerLat, $centerLng))
            ->zoom((int)$data[SettingName::MAP_CENTER_ZOOM->value]['value']);

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
                static fn(array $ap): array => [
                    'id' => $ap['id'],
                    'name' => $ap['name'] ?? $ap['ssid'] ?? 'Access Point Sem Nome',
                    'lat' => (float) $ap['lat'],
                    'lng' => (float) $ap['lng'],
                    'ssid' => $ap['ssid'] ?? null,
                    'macAddress' => $ap['macAddress'] ?? null,
                    'vendor' => $ap['vendor'] ?? null,
                    'model' => $ap['model'] ?? null,
                    'standard' => $ap['standard'] ?? null,
                    'serialNumber' => $ap['serialNumber'] ?? null,
                    'altitudeMsl' => isset($ap['altitudeMsl']) ? (float) $ap['altitudeMsl'] : null,
                    'altitudeAgl' => isset($ap['altitudeAgl']) ? (float) $ap['altitudeAgl'] : null,
                ],
                $accessPoints
            ),
        ]);
    }

    #[Route('/dashboard/map/networks', name: 'admin_dashboard_map_networks', methods: ['GET'])]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function networks(): Response
    {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'data' => $data,
            'searchTerm' => null,
        ]);
    }

    /**
     * @throws JsonException
     */
    #[Route('dashboard/map/network/create', name: 'admin_dashboard_map_network_create')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function createNetwork(Request $request): ?Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
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
            if ($data[SettingName::MAP_ENABLED->value]['value'] === OperationMode::OFF->value) {
                $this->addFlash('warning_map_disabled', true);
            }
            return $this->redirectToRoute('admin_dashboard_map_networks');
        }

        $centerLat = (float)$data[SettingName::MAP_CENTER_LATITUDE->value]['value'];
        $centerLng = (float)$data[SettingName::MAP_CENTER_LONGITUDE->value]['value'];

        $map = new Map()
            ->center(new Point($centerLat, $centerLng))
            ->zoom((int)$data[SettingName::MAP_CENTER_ZOOM->value]['value']);
        return $this->render('dashboard/shared/settings_actions/map/network/manage_network.html.twig', [
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
        return $this->redirectToRoute('admin_dashboard_map_networks');
    }

    /**
     * @throws JsonException
     */
    #[Route('dashboard/map/network/edit/{id:network<\d+>}', name: 'admin_dashboard_map_network_edit')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function editNetwork(Network $network, Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
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
            return $this->redirectToRoute('admin_dashboard_map_networks');
        }

        $centerLat = (float)$data[SettingName::MAP_CENTER_LATITUDE->value]['value'];
        $centerLng = (float)$data[SettingName::MAP_CENTER_LONGITUDE->value]['value'];

        $map = new Map()
            ->center(new Point($centerLat, $centerLng))
            ->zoom((int)$data[SettingName::MAP_CENTER_ZOOM->value]['value']);

        $accessPointsForMap = $this->entityManager->getRepository(AccessPoint::class)
            ->createQueryBuilder('ap')
            ->select('ap.id', 'ap.name', 'ap.location')
            ->where('ap.network = :network')
            ->andWhere('ap.location IS NOT NULL')
            ->setParameter('network', $network)
            ->getQuery()
            ->getArrayResult();

        $unmappedCount = 0;

        foreach ($accessPointsForMap as $apData) {
            $locationJson = $apData['location'];
            $location = is_string($locationJson) ? json_decode($locationJson, true) : $locationJson;

            if (is_array($location) && isset($location['coordinates'][0], $location['coordinates'][1])) {
                $lng = (float)$location['coordinates'][0];
                $lat = (float)$location['coordinates'][1];

                if ($lat === 0.0 && $lng === 0.0) {
                    $unmappedCount++;
                    continue;
                }

                $map->addMarker(new Marker(
                    position: new Point($lat, $lng),
                    title: $apData['name'] ?? 'Access Point'
                ));
            } else {
                $unmappedCount++;
            }
        }

        $nullLocationCount = (int) $this->entityManager->getRepository(AccessPoint::class)
            ->createQueryBuilder('ap')
            ->select('COUNT(ap.id)')
            ->where('ap.network = :network')
            ->andWhere('ap.location IS NULL')
            ->setParameter('network', $network)
            ->getQuery()
            ->getSingleScalarResult();

        $totalUnmapped = $unmappedCount + $nullLocationCount;

        return $this->render('dashboard/shared/settings_actions/map/network/manage_network.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
            'networkDTO' => $networkDTO,
            'network' => $network,
            'unmappedCount' => $totalUnmapped,
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

    #[Route(
        'dashboard/map/network/{id:network<\d+>}/accessPoints/create',
        name: 'admin_dashboard_map_accessPoint_create'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function networkAccessPointsCreate(Network $network, Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();
        $accessPointDTO = new AccessPointDTO();
        $accessPointDTO->network = $network;
        if ($accessPointDTO->latitude !== null && $accessPointDTO->longitude !== null) {
            $centerLat = $accessPointDTO->latitude;
            $centerLng = $accessPointDTO->longitude;
        } else {
            $centerLat = (float)$data[SettingName::MAP_CENTER_LATITUDE->value]['value'];
            $centerLng = (float)$data[SettingName::MAP_CENTER_LONGITUDE->value]['value'];
        }
        $zoom = (int)$data[SettingName::MAP_CENTER_ZOOM->value]['value'];

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
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();
        $accessPointDTO = AccessPointDTO::createFromEntity($accessPoint);
        if ($accessPointDTO->latitude !== null && $accessPointDTO->longitude !== null) {
            $centerLat = $accessPointDTO->latitude;
            $centerLng = $accessPointDTO->longitude;
        } else {
            $centerLat = (float)$data[SettingName::MAP_CENTER_LATITUDE->value]['value'];
            $centerLng = (float)$data[SettingName::MAP_CENTER_LONGITUDE->value]['value'];
        }
        $zoom = (int)$data[SettingName::MAP_CENTER_ZOOM->value]['value'];

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
        return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
    }

    #[Route('dashboard/map/settings', name: 'admin_dashboard_map_settings')]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function mapSettings(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();
        $dto = new MapSettingsDTO($data);

        $map = new Map()
            ->center(new Point((float)$dto->latitude, (float)$dto->longitude))
            ->zoom($dto->zoom);

        $form = $this->createForm(MapSettingsType::class, $dto);
        $form->handleRequest($request);

        $canWrite = $this->isGranted(UserAuthenticationVoter::MAP_WRITE);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            $changeset = $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

            // Track event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_MAP_REQUEST->value,
                new DateTime(),
                [
                    EventMetadataKeysType::IP->value => $request->getClientIp(),
                    EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                    EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                    EventMetadataKeysType::CHANGESET->value => $changeset
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('mapSettingsAppliedSuccessfully', [], 'controllers')
            );
        }

        return $this->render('dashboard/shared/settings_actions/map/settings.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'map' => $map,
        ]);
    }

    /**
     * @return array{float, float, float, float}|Response
     */
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
        if (!$network->getId()) {
            return [];
        }

        $accessPoints = $this->accessPointRepository->findByNetworkWithCoordinates($network);

        $excludeId = $excludeAccessPoint?->getId();
        $result = [];

        foreach ($accessPoints as $ap) {
            if ($excludeId !== null && $ap['id'] === $excludeId) {
                continue;
            }

            $result[] = [
                'lat' => (float) $ap['lat'],
                'lng' => (float) $ap['lng'],
                'name' => $ap['name'] ?? $ap['ssid'],
            ];
        }

        return $result;
    }
}
