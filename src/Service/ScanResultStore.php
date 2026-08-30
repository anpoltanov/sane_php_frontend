<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\ScanNotFoundException;

class ScanResultStore
{
    public function __construct(
        private readonly ScanConverter $converter,
        private readonly string $directory,
        private readonly int $ttlSeconds = 900,
    ) {
    }

    /**
     * @return array{id: string, fileName: string, deviceName: string, resolution: int, createdAt: int}
     */
    public function save(string $canonicalSourcePath, string $fileName, string $deviceName, int $resolution): array
    {
        $this->cleanupExpired();
        $this->ensureDirectory($this->directory);

        $id = bin2hex(random_bytes(16));
        $scanDir = $this->directory.'/'.$id;
        $this->ensureDirectory($scanDir);

        $canonicalPath = $scanDir.'/canonical.jpg';
        $previewPath = $scanDir.'/preview.jpg';

        if (!@rename($canonicalSourcePath, $canonicalPath) && !@copy($canonicalSourcePath, $canonicalPath)) {
            throw new Exception\RuntimeException('Unable to store scanned image');
        }

        $this->converter->generatePreview($canonicalPath, $previewPath);

        $meta = [
            'id' => $id,
            'fileName' => $fileName,
            'deviceName' => $deviceName,
            'resolution' => $resolution,
            'createdAt' => time(),
        ];
        $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($scanDir.'/meta.json', $encoded) === false) {
            throw new Exception\RuntimeException('Unable to store scan metadata');
        }

        return $meta;
    }

    /**
     * @return array{id: string, fileName: string, deviceName: string, resolution: int, createdAt: int, canonicalPath: string, previewPath: string}
     */
    public function get(string $id): array
    {
        $this->assertId($id);
        $scanDir = $this->directory.'/'.$id;
        $metaFile = $scanDir.'/meta.json';
        $canonicalPath = $scanDir.'/canonical.jpg';
        $previewPath = $scanDir.'/preview.jpg';

        if (!is_file($metaFile) || !is_file($canonicalPath)) {
            throw new ScanNotFoundException('Scan not found or expired');
        }

        $meta = json_decode((string) file_get_contents($metaFile), true);
        if (!is_array($meta)) {
            throw new ScanNotFoundException('Scan metadata is invalid');
        }

        $createdAt = (int) ($meta['createdAt'] ?? 0);
        if ($createdAt > 0 && (time() - $createdAt) > $this->ttlSeconds) {
            $this->removeDir($scanDir);
            throw new ScanNotFoundException('Scan not found or expired');
        }

        $meta['canonicalPath'] = $canonicalPath;
        $meta['previewPath'] = is_file($previewPath) ? $previewPath : $canonicalPath;

        return $meta;
    }

    public function export(string $id, string $format): string
    {
        $scan = $this->get($id);
        $format = strtolower($format);
        $destination = dirname($scan['canonicalPath']).'/export.'.$format;
        $this->converter->convert($scan['canonicalPath'], $destination, $format);

        return $destination;
    }

    public function cleanupExpired(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $metaFile = $dir.'/meta.json';
            $createdAt = is_file($metaFile)
                ? (int) (json_decode((string) file_get_contents($metaFile), true)['createdAt'] ?? filemtime($metaFile))
                : (int) filemtime($dir);
            if ((time() - $createdAt) > $this->ttlSeconds) {
                $this->removeDir($dir);
            }
        }
    }

    private function assertId(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new ScanNotFoundException('Scan not found or expired');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new Exception\RuntimeException(sprintf('Unable to create directory %s', $directory));
        }
    }

    private function removeDir(string $directory): void
    {
        $files = glob($directory.'/{*,.[!.]*}', GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($directory);
    }
}
