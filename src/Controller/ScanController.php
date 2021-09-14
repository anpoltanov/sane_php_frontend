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

/**
 * Class ScanController
 * @package App\Controller
 */
class ScanController extends AbstractController
{
    /**
     * @param Request $request
     * @param ScanImage $scanImageService
     * @return Response
     * @Route("/", name="scan")
     */
    public function indexAction(Request $request, ScanImage $scanImageService): Response
    {
        $success = $error = false;
        $message = null;
        $scanTask = new ScanTask();

        /** ---- BEGINOF @TODO Cache this (Redis) or store in persistent DB (SQLite or Mongo?) ------ */
        $scanner = $scanImageService->getScanners();
        if (empty($scanner)) {
            $error = true;
            $message = 'No scanners found';
        }
        // @TODO implement scanner selector
        $scanner = array_pop($scanner);
        $scannerOptions = $scanImageService->getScannerOptions($scanner);
        if (!$error && empty($scannerOptions)) {
            $error = true;
            $message = 'Could not obtain scanner options';
        }
        /** ---- ENDOF Cache this! ------ */

        $scanTask->setAvailableResolutions($scannerOptions['resolutions']); // @TODO encapsulate scanner options in class
        $form = $this->createForm(ScanType::class, $scanTask);
        $form->handleRequest($request);
        if (!$error && $form->isSubmitted() && $form->isValid()) {
            try {
                $message = $scanImageService->scanImage($scanTask);
                $success = true;
            } catch (RuntimeException $e) {
                $error = true;
                $message = $e->getMessage();
            }
        }
        return $this->render('scan/scan.html.twig', [
            'form' => $form->createView(),
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