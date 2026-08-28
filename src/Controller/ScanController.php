<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ScanTask;
use App\Form\Type\ScanType;
use App\Service\DownloadResponseFactory;
use App\Service\Exception\RuntimeException as ServiceRuntimeException;
use App\Service\Exception\ScanNotFoundException;
use App\Service\ScanConverter;
use App\Service\ScanImage;
use App\Service\ScanResultStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ScanController extends AbstractController
{
    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/', name: 'scan', methods: ['GET'])]
    public function indexAction(ScanImage $scanImageService, CacheInterface $cache): Response
    {
        $success = $error = false;
        $message = null;
        $form = null;
        $scanTask = new ScanTask();

        try {
            $scanners = $this->scanners($scanImageService, $cache);
            if ($scanners === []) {
                throw new Exception\RuntimeException('No scanners found');
            }

            $device = $scanners[0];
            $scanTask->setDeviceName($device);
            $scannerOptions = $this->scannerOptions($scanImageService, $cache, $device);
            $form = $this->createScanForm($scanTask, $scanners, $scannerOptions['resolutions']);
        } catch (ServiceRuntimeException|Exception\RuntimeException $e) {
            $error = true;
            $message = $e->getMessage();
            $cache->delete('scanners_list');
        }

        return $this->render('scan/scan.html.twig', [
            'form' => $form?->createView(),
            'success' => $success,
            'error' => $error,
            'message' => $message,
            'formats' => ScanConverter::formats(),
        ]);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/scan', name: 'scan_create', methods: ['POST'])]
    public function createAction(
        Request $request,
        ScanImage $scanImageService,
        ScanResultStore $scanStore,
        CacheInterface $cache,
    ): JsonResponse {
        try {
            $scanners = $this->scanners($scanImageService, $cache);
            if ($scanners === []) {
                throw new Exception\RuntimeException('No scanners found');
            }

            $scanTask = new ScanTask();
            $scanTask->setDeviceName($scanners[0]);
            $initialOptions = $this->scannerOptions($scanImageService, $cache, $scanners[0]);
            $form = $this->createScanForm($scanTask, $scanners, $initialOptions['resolutions']);
            $form->handleRequest($request);

            if (!$form->isSubmitted() || !$form->isValid()) {
                return $this->json(['error' => $this->formError($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'sane_scan_');
            if ($tempFile === false) {
                throw new ServiceRuntimeException('Unable to create a temporary file');
            }

            try {
                $scanImageService->scanToFile($scanTask, $tempFile);
                $meta = $scanStore->save(
                    $tempFile,
                    $scanTask->getFileName(),
                    $scanTask->getDeviceName(),
                    (int) $scanTask->getResolution()
                );
            } finally {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }

            return $this->json([
                'scanId' => $meta['id'],
                'fileName' => $meta['fileName'],
                'previewUrl' => $this->generateUrl('scan_preview', ['id' => $meta['id']]),
            ]);
        } catch (ServiceRuntimeException|Exception\RuntimeException $e) {
            $cache->delete('scanners_list');

            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/scan/{id}/preview', name: 'scan_preview', methods: ['GET'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function previewAction(
        string $id,
        ScanResultStore $scanStore,
        DownloadResponseFactory $downloadResponseFactory,
    ): Response {
        try {
            $scan = $scanStore->get($id);
        } catch (ScanNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return $downloadResponseFactory->inline($scan['previewPath'], ScanConverter::MIME_TYPES[ScanConverter::FORMAT_JPEG]);
    }

    #[Route('/scan/{id}/download', name: 'scan_download', methods: ['GET'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function downloadAction(
        string $id,
        Request $request,
        ScanResultStore $scanStore,
        ScanConverter $converter,
        DownloadResponseFactory $downloadResponseFactory,
    ): Response {
        $format = strtolower((string) $request->query->get('format', ScanConverter::FORMAT_JPEG));
        if (!in_array($format, ScanConverter::formats(), true)) {
            throw new BadRequestHttpException('Unsupported file format');
        }

        try {
            $scan = $scanStore->get($id);
            $filePath = $scanStore->export($id, $format);
        } catch (ScanNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (ServiceRuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $fileName = trim((string) $request->query->get('filename', $scan['fileName']));
        if ($fileName === '') {
            $fileName = $scan['fileName'];
        }
        $fileName = mb_substr($fileName, 0, 50);

        return $downloadResponseFactory->attachment(
            $filePath,
            sprintf('%s.%s', $fileName, $format),
            $converter->mimeType($format)
        );
    }

    #[Route('/scanner/options', name: 'scanner_options', methods: ['GET'])]
    public function getScannerOptionsAction(
        Request $request,
        ScanImage $scanImageService,
        CacheInterface $cache,
    ): Response {
        $device = $request->query->get('device');
        if (!is_string($device) || trim($device) === '') {
            throw new BadRequestHttpException('Invalid device parameter.');
        }
        $device = trim($device);

        try {
            $message = $this->scannerOptions($scanImageService, $cache, $device);
        } catch (ServiceRuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return (new JsonResponse($message))->setEncodingOptions(
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK
        );
    }

    #[Route('/scanner', name: 'scanner_index', methods: ['GET'])]
    public function getScannersAction(ScanImage $scanImageService): Response
    {
        return (new JsonResponse($scanImageService->getScanners()))->setEncodingOptions(
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK
        );
    }

    /**
     * @param string[] $scanners
     * @param int[] $resolutions
     */
    private function createScanForm(ScanTask $scanTask, array $scanners, array $resolutions): FormInterface
    {
        return $this->createForm(ScanType::class, $scanTask, [
            'scanners' => $scanners,
            'resolutions' => $resolutions,
            'action' => $this->generateUrl('scan_create'),
            'method' => 'POST',
        ]);
    }

    /**
     * @return string[]
     */
    private function scanners(ScanImage $scanImageService, CacheInterface $cache): array
    {
        return $cache->get('scanners_list', function (ItemInterface $item) use ($scanImageService) {
            $item->expiresAfter(3600);

            return $scanImageService->getScanners();
        });
    }

    /**
     * @return array{resolutions: int[]}
     */
    private function scannerOptions(ScanImage $scanImageService, CacheInterface $cache, string $device): array
    {
        $cacheKey = 'scanner_options_'.md5($device);

        return $cache->get($cacheKey, function (ItemInterface $item) use ($scanImageService, $device) {
            $item->expiresAfter(3600);

            return $scanImageService->getScannerOptions($device);
        });
    }

    private function formError(FormInterface $form): string
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return $errors !== [] ? implode(' ', $errors) : 'Invalid scan parameters';
    }
}
