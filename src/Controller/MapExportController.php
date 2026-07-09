<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AdminPermissionsType;
use App\Repository\NetworkRepository;
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
use Throwable;

class MapExportController extends AbstractController
{
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
                        'network_name', 'network_description', 'network_geometry',
                        'ap_name', 'ap_ssid', 'ap_mac_address', 'ap_vendor',
                        'ap_model', 'ap_standard', 'ap_serial_number',
                        'ap_longitude', 'ap_latitude', 'ap_altitude_msl', 'ap_altitude_agl'
                    ],
                    escape: '\\'
                );

                $networks = $networkRepository->createQueryBuilder('n')
                    ->leftJoin('n.accessPoints', 'ap')
                    ->addSelect('ap')
                    ->getQuery()
                    ->getResult();

                foreach ($networks as $network) {
                    $netName = $network->getName();
                    $netDesc = $network->getDescription();

                    $geo = $network->getGeometry();
                    $netGeo = is_array($geo) ? json_encode(
                        $geo,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                    ) : (string)$geo;

                    $aps = $network->getAccessPoints();

                    if ($aps->isEmpty()) {
                        fputcsv(
                            $handle,
                            [
                                $netName, $netDesc, $netGeo,
                                '', '', '', '', '', '', '', '', '', '', ''
                            ],
                            escape: '\\'
                        );
                        continue;
                    }

                    foreach ($aps as $ap) {
                        $locationData = $ap->getLocationData();

                        $lng = '';
                        $lat = '';

                        if ($locationData !== null) {
                            $lng = $locationData['lng'];
                            $lat = $locationData['lat'];
                        }

                        fputcsv(
                            $handle,
                            [
                                $netName,
                                $netDesc,
                                $netGeo,
                                $ap->getName(),
                                $ap->getSsid(),
                                $ap->getMacAddress(),
                                $ap->getVendor(),
                                $ap->getModel(),
                                $ap->getStandard(),
                                $ap->getSerialNumber(),
                                $lng !== '' ? number_format((float)$lng, 6, '.', '') : '',
                                $lat !== '' ? number_format((float)$lat, 6, '.', '') : '',
                                $ap->getAltitudeMsl(),
                                $ap->getAltitudeAgl()
                            ],
                            escape: '\\'
                        );
                    }
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
    public function importCsv(Request $request, EntityManagerInterface $em, NetworkRepository $networkRepository): Response
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('import_file');

        if (!$file) {
            $this->addFlash('error', 'No file was uploaded.');
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        if ($file->getClientOriginalExtension() !== 'csv') {
            $this->addFlash('error', 'Invalid file format. Please upload a valid CSV file.');
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || !is_readable($realPath)) {
            $this->addFlash('error', 'The uploaded file is not readable.');
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        $handle = fopen($realPath, 'r');
        if ($handle === false) {
            $this->addFlash('error', 'Could not open the uploaded CSV file.');
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ',');
        if (!$headers || !in_array('network_name', $headers, true)) {
            fclose($handle);
            $this->addFlash('error', 'Invalid CSV structure. The column "network_name" is mandatory.');
            return $this->redirectToRoute('admin_dashboard_map_network_list');
        }

        $networksCreatedOrUpdated = [];
        $apsImportedCount = 0;
        $now = new \DateTimeImmutable();

        try {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $netName    = trim($row[0] ?? '');
                $netDesc    = trim($row[1] ?? '');
                $netGeoRaw  = trim($row[2] ?? '');

                $apName     = trim($row[3] ?? '');
                $apSsid     = trim($row[4] ?? '');
                $apMac      = trim($row[5] ?? '');
                $apVendor   = trim($row[6] ?? '');
                $apModel    = trim($row[7] ?? '');
                $apStandard = trim($row[8] ?? '');
                $apSerial   = trim($row[9] ?? '');
                $apLng      = trim($row[10] ?? '');
                $apLat      = trim($row[11] ?? '');
                $apAltMsl   = trim($row[12] ?? '');
                $apAltAgl   = trim($row[13] ?? '');

                if (empty($netName)) {
                    continue;
                }

                if (!isset($networksCreatedOrUpdated[$netName])) {
                    $network = $networkRepository->findOneBy(['name' => $netName]);
                    $isNew = false;

                    if (!$network) {
                        $network = new Network();
                        $network->setName($netName);
                        $network->setCreatedAt($now);
                        $isNew = true;
                    }

                    $network->setUpdatedAt($now);

                    if (!empty($netDesc)) {
                        $network->setDescription($netDesc);
                    }

                    if (!empty($netGeoRaw)) {
                        $network->setGeometry($netGeoRaw);
                    } elseif ($isNew) {
                        $network->setGeometry(json_encode([
                            'type' => 'GeometryCollection',
                            'geometries' => []
                        ], JSON_THROW_ON_ERROR));
                    }

                    $em->persist($network);
                    $networksCreatedOrUpdated[$netName] = $network;
                } else {
                    $network = $networksCreatedOrUpdated[$netName];
                }

                if (!empty($apName)) {
                    $existingAp = null;
                    foreach ($network->getAccessPoints() as $currentAp) {
                        if (!empty($apMac) && $currentAp->getMacAddress() === $apMac) {
                            $existingAp = $currentAp;
                            break;
                        }
                        if (empty($apMac) && $currentAp->getName() === $apName) {
                            $existingAp = $currentAp;
                            break;
                        }
                    }

                    if ($existingAp) {
                        $ap = $existingAp;
                    } else {
                        $ap = new AccessPoint();
                        $ap->setCreatedAt($now);
                        $network->addAccessPoint($ap);
                    }

                    $ap->setName($apName);
                    $ap->setSsid(!empty($apSsid) ? $apSsid : 'OpenRoaming');
                    $ap->setMacAddress(!empty($apMac) ? $apMac : null);
                    $ap->setVendor(!empty($apVendor) ? $apVendor : null);
                    $ap->setModel(!empty($apModel) ? $apModel : null);
                    $ap->setStandard(!empty($apStandard) ? $apStandard : null);
                    $ap->setSerialNumber(!empty($apSerial) ? $apSerial : null);
                    $ap->setUpdatedAt($now);

                    if ($apLng !== '' && $apLat !== '') {
                        $latFloat = (float)$apLat;
                        $lngFloat = (float)$apLng;

                        $ap->setLocation(json_encode([
                            'type' => 'Point',
                            'coordinates' => [$lngFloat, $latFloat]
                        ], JSON_THROW_ON_ERROR));
                    }

                    $ap->setAltitudeMsl($apAltMsl !== '' ? (float)$apAltMsl : null);
                    $ap->setAltitudeAgl($apAltAgl !== '' ? (float)$apAltAgl : null);

                    $em->persist($ap);

                    if (!$existingAp) {
                        $apsImportedCount++;
                    }
                }
            }

            $em->flush();
            fclose($handle);

            $this->addFlash('success', sprintf(
                'Import successful! Processed %d networks and imported/updated %d Access Points.',
                count($networksCreatedOrUpdated),
                $apsImportedCount
            ));

        } catch (\Throwable $e) {
            fclose($handle);
            $this->addFlash('error', 'An error occurred during import: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_dashboard_map');
    }
}
