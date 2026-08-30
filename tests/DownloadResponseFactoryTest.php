<?php

declare(strict_types=1);

namespace App\Test;

use App\Service\DownloadResponseFactory;
use PHPUnit\Framework\TestCase;

class DownloadResponseFactoryTest extends TestCase
{
    public function testCyrillicFilenameGetsRfc5987Disposition(): void
    {
        $factory = new DownloadResponseFactory();
        $disposition = $this->disposition($factory, 'скан.pdf');

        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('filename*=utf-8\'\'', $disposition);
        $this->assertStringContainsString(rawurlencode('скан.pdf'), $disposition);
        $this->assertMatchesRegularExpression('/filename="?[^";=]+"?/', $disposition);
        $this->assertDoesNotMatchRegularExpression('/filename="[^"]*[^\x20-\x7e][^"]*"/', $disposition);
    }

    public function testAsciiFilenameDoesNotNeedUtf8FallbackDifference(): void
    {
        $factory = new DownloadResponseFactory();
        $fallback = $factory->asciiFallback('scan-file.jpeg');

        $this->assertSame('scan-file.jpeg', $fallback);
    }

    public function testAsciiFallbackTransliteratesCyrillic(): void
    {
        $factory = new DownloadResponseFactory();
        $fallback = $factory->asciiFallback('скан.png');

        $this->assertNotSame('', $fallback);
        $this->assertMatchesRegularExpression('/^[\x20-\x7e]+$/', $fallback);
        $this->assertStringEndsWith('.png', $fallback);
    }

    public function testPathSeparatorsAreStrippedFromFilename(): void
    {
        $factory = new DownloadResponseFactory();

        $this->assertSame('a_b.png', $factory->sanitizeFilename("a/b.png"));
    }

    private function disposition(DownloadResponseFactory $factory, string $filename): string
    {
        $file = tempnam(sys_get_temp_dir(), 'sane_dl_');
        self::assertNotFalse($file);
        file_put_contents($file, 'x');

        try {
            $response = $factory->attachment($file, $filename, 'application/pdf');

            return (string) $response->headers->get('Content-Disposition');
        } finally {
            @unlink($file);
        }
    }
}
