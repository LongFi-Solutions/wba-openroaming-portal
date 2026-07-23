<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AccessPointDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Enum\AdminPermissionsType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MapAccessPointExportController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Export only this network's access points.
     */
    #[Route(
        'dashboard/map/network/{id:network<\d+>}/accessPoints/export',
        name: 'admin_dashboard_map_network_accessPoints_export'
    )]
    #[isGranted(AdminPermissionsType::MAP_READ->value)]
    public function exportToCsv(Network $network): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($network): void {
            $handle = fopen('php://output', 'wb+');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the CSV.');
            }

            fwrite($handle, "\xEF\xBB\xBF");

            try {
                fputcsv(
                    $handle,
                    [
                        'ap_name',
                        'ap_ssid',
                        'ap_mac_address',
                        'ap_vendor',
                        'ap_model',
                        'ap_standard',
                        'ap_serial_number',
                        'ap_longitude',
                        'ap_latitude',
                        'ap_altitude_msl',
                        'ap_altitude_agl'
                    ],
                    escape: '\\'
                );

                foreach ($network->getAccessPoints() as $ap) {
                    $locationRaw = $ap->getLocation();

                    $lng = '';
                    $lat = '';

                    if (is_string($locationRaw) && json_validate($locationRaw)) {
                        $parsed = json_decode($locationRaw, true, 512, JSON_THROW_ON_ERROR);
                        if (
                            isset($parsed['coordinates']) &&
                            is_array($parsed['coordinates']) &&
                            count($parsed['coordinates']) >= 2
                        ) {
                            $lng = $parsed['coordinates'][0];
                            $lat = $parsed['coordinates'][1];
                        }
                    }

                    if (in_array($lng, [0, 0.0, '0'], true)) {
                        $lng = '';
                        $lat = '';
                    }

                    fputcsv(
                        $handle,
                        [
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

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $network->getName() ?? 'network');

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="export_%s_access_points.csv"', $safeName)
        );

        return $response;
    }

    /**
     * Import access points into this network only. Does not create/touch other networks.
     */
    #[Route(
        'dashboard/map/network/{id:network<\d+>}/accessPoints/import',
        name: 'admin_dashboard_map_network_accessPoints_import'
    )]
    #[isGranted(AdminPermissionsType::MAP_WRITE->value)]
    public function importCsv(
        Network $network,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('import_file');

        if (!$file) {
            $this->addFlash('error', $this->translator->trans('importErrorNoFile', [], 'controllers'));
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                ['id' => $network->getId()]
            );
        }

        if ($file->getClientOriginalExtension() !== 'csv') {
            $this->addFlash('error', $this->translator->trans('importErrorInvalidFormat', [], 'controllers'));
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                ['id' => $network->getId()]
            );
        }

        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/x-csv',
            'application/vnd.ms-excel',
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            $this->addFlash('error', $this->translator->trans('importErrorInvalidMimeType', [], 'controllers'));
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                ['id' => $network->getId()]
            );
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || !is_readable($realPath)) {
            $this->addFlash('error', $this->translator->trans('importErrorNotReadable', [], 'controllers'));
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                ['id' => $network->getId()]
            );
        }

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            $this->addFlash('error', $this->translator->trans('importErrorCannotOpen', [], 'controllers'));
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                ['id' => $network->getId()]
            );
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ',', escape: '\\');
        if (!$headers || !in_array(
                'ap_name',
                $headers,
                true
            )) {
            fclose($handle);
            $this->addFlash(
                'error',
                $this->translator->trans('importErrorInvalidStructure', [], 'controllers')
            );
            return $this->redirectToRoute(
                'admin_dashboard_map_network_accessPoints',
                ['id' => $network->getId()]
            );
        }

        $apsImportedCount = 0;
        $apsUpdatedCount = 0;
        $rowErrors = [];   // structured, not a flat string
        $validRows = [];   // dto + raw fields, kept if valid
        $rowNumber = 1;

        try {
            while (($row = fgetcsv($handle, 0, ',', escape: '\\')) !== false) {
                $rowNumber++;

                $apName = trim($row[0] ?? '');
                if ($apName === '' || $apName === '0') {
                    continue; // truly empty row, not a data error
                }

                $apSsid = trim($row[1] ?? '');
                $apMac = trim($row[2] ?? '');
                $apVendor = trim($row[3] ?? '');
                $apModel = trim($row[4] ?? '');
                $apStandard = trim($row[5] ?? '');
                $apSerial = trim($row[6] ?? '');
                $apLng = trim($row[7] ?? '');
                $apLat = trim($row[8] ?? '');
                $apAltMsl = trim($row[9] ?? '');
                $apAltAgl = trim($row[10] ?? '');

                // Build the DTO exactly like the create/edit form would.
                $dto = new AccessPointDTO();
                $dto->network = $network;
                $dto->name = $apName;
                $dto->ssid = $apSsid === '' || $apSsid === '0' ? 'OpenRoaming' : $apSsid;
                $dto->macAddress = $apMac === '' || $apMac === '0' ? null : $apMac;
                $dto->vendor = $apVendor === '' || $apVendor === '0' ? null : $apVendor;
                $dto->model = $apModel === '' || $apModel === '0' ? null : $apModel;
                $dto->standard = $apStandard === '' || $apStandard === '0' ? null : $apStandard;
                $dto->serialNumber = $apSerial === '' || $apSerial === '0' ? null : $apSerial;
                $dto->latitude = $apLat !== '' ? $apLat : null;
                $dto->longitude = $apLng !== '' ? $apLng : null;
                $dto->altitudeMsl = $apAltMsl !== '' ? (float)$apAltMsl : null;
                $dto->altitudeAgl = $apAltAgl !== '' ? (float)$apAltAgl : null;

                $violations = $this->validator->validate($dto);

                if (count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $rowErrors[] = [
                            'row' => $rowNumber,
                            'name' => $apName,
                            'field' => $violation->getPropertyPath() ?: 'general',
                            'message' => $violation->getMessage(),
                        ];
                    }
                    continue;
                }

                $validRows[] = ['dto' => $dto, 'mac' => $apMac, 'name' => $apName];
            }

            fclose($handle);

            // Atomic: any error at all → abort, write nothing.
            if (!empty($rowErrors)) {
                $groupedErrors = [];
                foreach ($rowErrors as $err) {
                    $key = $err['field'] . '|' . $err['message'];
                    if (!isset($groupedErrors[$key])) {
                        $groupedErrors[$key] = [
                            'field' => $err['field'],
                            'message' => $err['message'],
                            'rows' => [],
                        ];
                    }
                    $groupedErrors[$key]['rows'][] = [
                        'row' => $err['row'],
                        'name' => $err['name'],
                    ];
                }

                usort($groupedErrors, static fn (array $a, array $b): int => count($b['rows']) <=> count($a['rows']));

                $this->addFlash('import_errors', array_values($groupedErrors));
                // no separate 'import_errors_total' flash — the template derives it
                $this->addFlash('error', $this->translator->trans('importErrorValidation', [], 'controllers'));
                return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
            }

            // Everything validated — now actually write it, inside one transaction.
            $em->wrapInTransaction(
                function () use ($em, $network, $validRows, &$apsImportedCount, &$apsUpdatedCount): void {
                    $now = new DateTimeImmutable();

                    foreach ($validRows as $entry) {
                        /** @var AccessPointDTO $dto */
                        $dto = $entry['dto'];
                        $apMac = $entry['mac'];
                        $apName = $entry['name'];

                        $existingAp = null;
                        foreach ($network->getAccessPoints() as $currentAp) {
                            if ($apMac !== '' && $apMac !== '0' && $currentAp->getMacAddress() === $apMac) {
                                $existingAp = $currentAp;
                                break;
                            }
                            if (($apMac === '' || $apMac === '0') && $currentAp->getName() === $apName) {
                                $existingAp = $currentAp;
                                break;
                            }
                        }

                        if ($existingAp) {
                            $ap = $existingAp;
                            $apsUpdatedCount++;
                        } else {
                            $ap = new AccessPoint();
                            $ap->setCreatedAt($now);
                            $network->addAccessPoint($ap);
                            $apsImportedCount++;
                        }

                        $dto->updateEntity($ap);
                        $em->persist($ap);
                    }

                    $em->persist($network);
                }
            );

            $this->addFlash(
                'success',
                sprintf(
                    $this->translator->trans('accessPointImportSuccess', [], 'controllers'),
                    $apsImportedCount,
                    $apsUpdatedCount
                )
            );
        } catch (Throwable $e) {
            fclose($handle);
            $this->addFlash(
                'error',
                sprintf(
                    $this->translator->trans('importErrorGeneral', [], 'controllers'),
                    $e->getMessage()
                )
            );
        }

        return $this->redirectToRoute('admin_dashboard_map_network_accessPoints', ['id' => $network->getId()]);
    }
}
