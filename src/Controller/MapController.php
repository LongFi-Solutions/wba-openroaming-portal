<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\NetworkDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Enum\AdminPermissionsType;
use App\Form\CreateNetworkType;
use App\Repository\AccessPointRepository;
use App\Repository\NetworkRepository;
use App\Service\GetSettings;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly AccessPointRepository $accessPointRepository,
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

    #[Route('dashboard/map/network/create',
        name: 'admin_dashboard_map_network_create')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function createNetwork(Request $request): ?Response
    {
        $data = $this->getSettings->getSettings();
        $networkDTO = new NetworkDTO();
        $form = $this->createForm(CreateNetworkType::class, $networkDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $network = new Network();
            $network->setName($networkDTO->name);
            if ($networkDTO->description !== null) {
                $network->setDescription($networkDTO->description);
            }
            $network->setCreatedAt(new DateTimeImmutable());
            $network->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($network);
            $this->entityManager->flush();
            return $this->redirectToRoute('admin_dashboard_map');
        }
        return $this->render('dashboard/shared/settings_actions/map/create.html.twig', [
            'form' => $form->createView(),
            'data' => $data,

        ]);

    }

    #[Route('dashboard/map/network/delete/{id:network<\d+>}',
        name: 'admin_dashboard_map_network_delete')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function deleteNetwork(Network $network): Response
    {
        $this->entityManager->remove($network);
        $this->entityManager->flush();
        return $this->redirectToRoute('admin_dashboard_map');
    }

    #[Route('dashboard/map/network/edit/{id:network<\d+>}',
        name: 'admin_dashboard_map_network_edit')]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function editNetwork(Network $network, Request $request): Response
    {
        $data = $this->getSettings->getSettings();
        $networkDTO = new NetworkDTO();
        $networkDTO->name = $network->getName();
        $networkDTO->description = $network->getDescription();
        $form = $this->createForm(CreateNetworkType::class, $networkDTO);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $network->setName($networkDTO->name);
            if ($networkDTO->description !== null) {
                $network->setDescription($networkDTO->description);
            }
            $network->setUpdatedAt(new DateTimeImmutable());
            $this->entityManager->persist($network);
            $this->entityManager->flush();
            return $this->redirectToRoute('admin_dashboard_map');
        }
        return $this->render('dashboard/shared/settings_actions/map/edit.html.twig', [
            'form' => $form->createView(),
            'data' => $data,

        ]);

    }

    #[Route('dashboard/map/network/{id:network<\d+>}/accessPoints',
        name: 'admin_dashboard_map_network_accessPoints')]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function NetworkAccessPoints(Network $network, Request $request): Response
    {
        $data = $this->getSettings->getSettings();

        return $this->render('dashboard/shared/settings_actions/map/access_points.html.twig', [
            'data' => $data,
            'network' => $network,
        ]);
    }

}
