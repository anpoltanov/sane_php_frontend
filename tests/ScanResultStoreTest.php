<?php

declare(strict_types=1);

namespace App\Test;

use App\Service\Exception\ScanNotFoundException;
use App\Service\ScanConverter;
use App\Service\ScanResultStore;
use PHPUnit\Framework\TestCase;

class ScanResultStoreTest extends TestCase
{
    public function testSaveAndGetRoundTrip(): void
    {
        $directory = sys_get_temp_dir().'/sane_store_'.uniqid('', true);
        $store = new ScanResultStore(new ScanConverter(), $directory, 900);

        try {
            $meta = $store->save($this->copyFixture(), 'скан', 'mock:scanner', 300);
            $loaded = $store->get($meta['id']);

            $this->assertSame('скан', $loaded['fileName']);
            $this->assertFileExists($loaded['canonicalPath']);
            $this->assertFileExists($loaded['previewPath']);
        } finally {
            $this->removeDir($directory);
        }
    }

    public function testExpiredScanIsRejected(): void
    {
        $directory = sys_get_temp_dir().'/sane_store_'.uniqid('', true);
        $store = new ScanResultStore(new ScanConverter(), $directory, 900);

        try {
            $meta = $store->save($this->copyFixture(), 'doc', 'mock:scanner', 300);
            $metaFile = $directory.'/'.$meta['id'].'/meta.json';
            $payload = json_decode((string) file_get_contents($metaFile), true);
            $payload['createdAt'] = time() - 901;
            file_put_contents($metaFile, json_encode($payload));

            $this->expectException(ScanNotFoundException::class);
            $store->get($meta['id']);
        } finally {
            $this->removeDir($directory);
        }
    }

    public function testInvalidIdIsRejected(): void
    {
        $store = new ScanResultStore(new ScanConverter(), sys_get_temp_dir().'/sane_store_invalid', 900);

        $this->expectException(ScanNotFoundException::class);
        $store->get('../etc/passwd');
    }

    private function copyFixture(): string
    {
        $source = dirname(__DIR__).'/tests/fixtures/sample.jpg';
        $copy = sys_get_temp_dir().'/sane_src_'.uniqid('', true).'.jpg';
        copy($source, $copy);

        return $copy;
    }

    private function removeDir(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (glob($directory.'/*') ?: [] as $child) {
            if (is_dir($child)) {
                $this->removeDir($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($directory);
    }
}
