<?php

declare(strict_types=1);

namespace App\Test;

use App\Service\ScanConverter;
use PHPUnit\Framework\TestCase;

class ScanConverterTest extends TestCase
{
    public function testJpegCopyKeepsFileReadable(): void
    {
        $converter = new ScanConverter();
        $destination = sys_get_temp_dir().'/sane_convert_'.uniqid('', true).'.jpg';

        try {
            $converter->convert($this->fixture(), $destination, ScanConverter::FORMAT_JPEG);
            $this->assertFileExists($destination);
            $this->assertGreaterThan(0, filesize($destination));
        } finally {
            @unlink($destination);
        }
    }

    public function testPreviewIsSmallerThanSourceWidth(): void
    {
        $converter = new ScanConverter();
        $destination = sys_get_temp_dir().'/sane_preview_'.uniqid('', true).'.jpg';

        try {
            $converter->generatePreview($this->fixture(), $destination, 16);
            $this->assertFileExists($destination);
            $info = getimagesize($destination);
            $this->assertNotFalse($info);
            $this->assertLessThanOrEqual(16, $info[0]);
        } finally {
            @unlink($destination);
        }
    }

    public function testPdfExport(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('imagick is required for PDF export');
        }

        $converter = new ScanConverter();
        $destination = sys_get_temp_dir().'/sane_convert_'.uniqid('', true).'.pdf';

        try {
            $converter->convert($this->fixture(), $destination, ScanConverter::FORMAT_PDF);
            $this->assertFileExists($destination);
            $this->assertGreaterThan(0, filesize($destination));
            $this->assertSame('application/pdf', $converter->mimeType(ScanConverter::FORMAT_PDF));
        } finally {
            @unlink($destination);
        }
    }

    private function fixture(): string
    {
        $path = dirname(__DIR__).'/tests/fixtures/sample.jpg';
        $this->assertFileExists($path);

        return $path;
    }
}
