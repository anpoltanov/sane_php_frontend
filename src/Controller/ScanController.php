<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ScanTask;
use App\Form\Type\ScanType;
use App\Service\Exception\RuntimeException;
use App\Service\ScanImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class ScanController
 * @package App\Controller
 */
class ScanController extends AbstractController
{
    /**
     * @param Request $request
     * @param ScanImage $scanImageService
     * @param CacheInterface $redisAdapter
     * @return Response
     * @throws \Psr\Cache\InvalidArgumentException
     * @Route("/", name="scan")
     */
    public function indexAction(Request $request, ScanImage $scanImageService, CacheInterface $redisAdapter): Response
    {
        $success = $error = false;
        $message = null;
        $scanTask = new ScanTask();

        try {
            // Acquire scanners list
            $scanner = $redisAdapter->get('scanners_list', function (ItemInterface $item) use ($scanImageService) {
                $item->expiresAfter(3600);
                return $scanImageService->getScanners();
            });
            if (empty($scanner)) {
                throw new Exception\RuntimeException('No scanners found');
            }

            // Acquire scanner options list
            // @TODO implement scanner selector
            $scanner = array_pop($scanner);
            $scannerOptions = $redisAdapter->get('current_scanner_props_list', function (ItemInterface $item) use ($scanImageService, $scanner) {
                $item->expiresAfter(3600);
                return $scanImageService->getScannerOptions($scanner);
            });
            if (empty($scannerOptions)) {
                throw new Exception\RuntimeException('Could not obtain scanner options');
            }

            // Create task and validate parameters
            $scanTask->setAvailableResolutions($scannerOptions['resolutions']); // @TODO encapsulate scanner options in class
            $form = $this->createForm(ScanType::class, $scanTask);
            $form->handleRequest($request);
            if (!$error && $form->isSubmitted() && $form->isValid()) {
                // Scan
                $message = $scanImageService->scanImage($scanTask);
                $success = true;
            }
        } catch (RuntimeException $e) {
            $error = true;
            $message = $e->getMessage();
            $redisAdapter->delete('scanners_list');
            $redisAdapter->delete('current_scanner_props_list');
        }

        return $this->render('scan/scan.html.twig', [
            'form' => !empty($form) ? $form->createView() : null,
            'success' => $success,
            'error' => $error,
            'message' => $message,
        ]);
    }

    /**
     * @param Request $request
     * @param ScanImage $scanImageService
     * @return Response
     * @Route("/scanner/options", name="scanner_options")
     */
    public function getScannerOptionsAction(Request $request, ScanImage $scanImageService): Response
    {
        $device = $request->query->getAlnum('device');
        if (empty($device)) {
            throw new \InvalidArgumentException('Invalid device parameter.');
        }
        $device = trim(escapeshellarg($device));

        try {
            $message = $scanImageService->getScannerOptions($device);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }
        return new JsonResponse(json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));
    }

    /**
     * @param Request $request
     * @param ScanImage $scanImageService
     * @return Response
     * @Route("/scanner", name="scanner_index")
     */
    public function getScannersAction(Request $request, ScanImage $scanImageService): Response
    {
        return new JsonResponse(json_encode($scanImageService->getScanners(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));
    }
}