<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AdminPermissionsType;
use App\Repository\NetworkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

class MapExportController extends AbstractController
{
    public function __construct()
    {
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

        $response = new StreamedResponse(function () use ($networkRepository) {
            $handle = fopen('php://output', 'wb+');

            fwrite($handle, "\xEF\xBB\xBF");

            try {
                fputcsv($handle, [
                    'network_name', 'network_description', 'network_geometry',
                    'ap_name', 'ap_ssid', 'ap_mac_address', 'ap_vendor',
                    'ap_model', 'ap_standard', 'ap_serial_number',
                    'ap_longitude', 'ap_latitude', 'ap_altitude_msl', 'ap_altitude_agl'
                ]);

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
                        fputcsv($handle, [
                            $netName, $netDesc, $netGeo,
                            '', '', '', '', '', '', '', '', '', '', ''
                        ]);
                        continue;
                    }

                    foreach ($aps as $ap) {
                        $location = $ap->getLocation();

                        $lng = '';
                        $lat = '';
                        if (is_array($location) && isset($location['coordinates'])) {
                            $lng = $location['coordinates'][0] ?? '';
                            $lat = $location['coordinates'][1] ?? '';
                        }

                        fputcsv($handle, [
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
                        ]);
                    }
                }
            } catch (Throwable $e) {
                fputcsv($handle, [
                    'FATAL ERROR:',
                    $e->getMessage(),
                    'LINE: ' . $e->getLine(),
                    'FILE: ' . $e->getFile()
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="export_openroaming_acores.csv"');

        return $response;
    }
}
