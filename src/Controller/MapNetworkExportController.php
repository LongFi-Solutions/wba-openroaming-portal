<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AdminPermissionsType;
use App\Repository\NetworkRepository;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Network;
use App\Entity\AccessPoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MapNetworkExportController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        'dashboard/map/export',
        name: 'admin_dashboard_map_export'
    )]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function exportToCsv(NetworkRepository $networkRepository, Request $request): StreamedResponse
    {
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }

        $response = new StreamedResponse(function () use ($networkRepository): void {
            $handle = fopen('php://output', 'wb+');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the CSV.');
            }

            fwrite($handle, "\xEF\xBB\xBF");

            try {
                fputcsv(
                    $handle,
                    [
                        'network_name',
                        'network_description',
                        'network_geometry'
                    ],
                    escape: '\\'
                );

                $networks = $networkRepository->createQueryBuilder('n')
                    ->getQuery()
                    ->getResult();

                foreach ($networks as $network) {
                    $netName = $network->getName();
                    $netDesc = $network->getDescription();

                    $geo = $network->getGeometry();
                    $netGeo = '';
                    if (is_array($geo)) {
                        $netGeo = json_encode($geo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    } elseif (is_string($geo)) {
                        $netGeo = $geo;
                    } elseif ($geo !== null) {
                        $netGeo = (string)$geo;
                    }

                    fputcsv(
                        $handle,
                        [
                            $netName,
                            $netDesc,
                            $netGeo
                        ],
                        escape: '\\'
                    );
                }
            } catch (Throwable $e) {
                fputcsv(
                    $handle,
                    [
                        'FATAL ERROR:',
                        $e->getMessage(),
                        'LINE: ' . $e->getLine(),
                        'FILE: ' . $e->getFile()
                    ],
                    escape: '\\'
                );
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="export_openroaming_networks.csv"');

        return $response;
    }

    #[Route(
        'dashboard/map/import',
        name: 'admin_dashboard_map_import'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function importCsv(
        Request $request,
        EntityManagerInterface $em,
        NetworkRepository $networkRepository,
    ): Response {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('import_file');

        if (!$file) {
            $this->addFlash('error', $this->translator->trans('importErrorNoFile', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        if ($file->getClientOriginalExtension() !== 'csv') {
            $this->addFlash('error', $this->translator->trans('importErrorInvalidFormat', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/x-csv',
            'application/vnd.ms-excel',
        ];

        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $this->addFlash('error', $this->translator->trans('importErrorInvalidMimeType', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || !is_readable($realPath)) {
            $this->addFlash('error', $this->translator->trans('importErrorNotReadable', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        $handle = fopen($realPath, 'r');
        if ($handle === false) {
            $this->addFlash('error', $this->translator->trans('importErrorCannotOpen', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ',', escape: '\\');
        if (!$headers || !in_array('network_name', $headers, true)) {
            fclose($handle);
            $this->addFlash('error', $this->translator->trans('importErrorInvalidStructure', [], 'controllers'));
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        $networksCreatedOrUpdated = [];
        $now = new DateTimeImmutable();

        try {
            while (($row = fgetcsv($handle, 0, ',', escape: '\\')) !== false) {
                $netName   = trim($row[0] ?? '');
                $netDesc   = trim($row[1] ?? '');
                $netGeoRaw = trim($row[2] ?? '');

                if ($netName === '' || $netName === '0') {
                    continue;
                }

                if (!isset($networksCreatedOrUpdated[$netName])) {
                    $network = $networkRepository->findOneBy(['name' => $netName]);
                    $isNew = false;

                    if (!$network instanceof Network) {
                        $network = new Network();
                        $network->setName($netName);
                        $network->setCreatedAt($now);
                        $isNew = true;
                    }

                    $network->setUpdatedAt($now);

                    if ($netDesc !== '' && $netDesc !== '0') {
                        $network->setDescription($netDesc);
                    }

                    if ($netGeoRaw !== '' && $netGeoRaw !== '0') {
                        if (json_validate($netGeoRaw)) {
                            $network->setGeometry($netGeoRaw);
                        } elseif ($isNew) {
                            $network->setGeometry(json_encode([
                                'type' => 'GeometryCollection',
                                'geometries' => []
                            ], JSON_THROW_ON_ERROR));
                        }
                    } elseif ($isNew) {
                        $network->setGeometry(json_encode([
                            'type' => 'GeometryCollection',
                            'geometries' => []
                        ], JSON_THROW_ON_ERROR));
                    }

                    $em->persist($network);
                    $networksCreatedOrUpdated[$netName] = $network;
                }
            }

            $em->flush();

            $this->addFlash('success', sprintf(
                $this->translator->trans('networkImportSuccess', [], 'controllers'),
                count($networksCreatedOrUpdated),
                0
            ));
        } catch (Throwable $e) {
            $this->addFlash('error', sprintf(
                $this->translator->trans('importErrorGeneral', [], 'controllers'),
                $e->getMessage()
            ));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $this->redirectToRoute('admin_dashboard_map');
    }
}
