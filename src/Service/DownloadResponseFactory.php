<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadResponseFactory
{
    public function attachment(string $filePath, string $downloadName, string $mimeType): StreamedResponse
    {
        $downloadName = $this->sanitizeFilename($downloadName);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $downloadName,
            $this->asciiFallback($downloadName)
        );

        return $this->stream($filePath, $disposition, $mimeType);
    }

    public function inline(string $filePath, string $mimeType): StreamedResponse
    {
        $disposition = HeaderUtils::DISPOSITION_INLINE;

        return $this->stream($filePath, $disposition, $mimeType);
    }

    public function asciiFallback(string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $transliterated = $filename;

        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII', $filename);
            if (is_string($converted) && $converted !== '') {
                $transliterated = $converted;
            }
        }

        $ascii = preg_replace('/[^\x20-\x7E]/', '', $transliterated) ?? '';
        $ascii = trim($ascii);
        if ($ascii === '' || $ascii === '.') {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $ascii = $extension !== '' ? 'scan.'.$extension : 'scan';
        }

        return $ascii;
    }

    public function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(["\0", '/', '\\'], '_', $filename);
        $filename = trim($filename);

        return $filename === '' ? 'scan' : $filename;
    }

    private function stream(string $filePath, string $disposition, string $mimeType): StreamedResponse
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new Exception\RuntimeException('Scan file is not readable');
        }

        $response = new StreamedResponse(static function () use ($filePath): void {
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                return;
            }
            fpassthru($handle);
            fclose($handle);
        });
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', (string) filesize($filePath));

        return $response;
    }
}
