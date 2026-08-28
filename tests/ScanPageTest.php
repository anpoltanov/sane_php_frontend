<?php

declare(strict_types=1);

namespace App\Test;

use App\Service\ScanImage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ScanPageTest extends WebTestCase
{
    public function testScanPageReturnsSuccessfulResponse(): void
    {
        $client = static::createClient();
        $this->mockScanImage($client);

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Scanner');
        $this->assertSelectorExists('select[name="scan[deviceName]"]');
        $this->assertSelectorNotExists('select[name="scan[extension]"]');
    }

    public function testScanFormIncludesCsrfToken(): void
    {
        $client = static::createClient();
        $this->mockScanImage($client);

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[type="hidden"][name$="[_token]"]')->count(),
            'CSRF token field should be present on the scan form'
        );
    }

    public function testPreviewPanelAndFormatChoicesAreRendered(): void
    {
        $client = static::createClient();
        $this->mockScanImage($client);

        $client->request('GET', '/');

        $this->assertSelectorExists('[data-controller="scan"]');
        $this->assertSelectorExists('input[name="downloadFormat"][value="pdf"]');
        $this->assertSelectorExists('input[name="downloadFormat"][value="jpeg"]');
    }

    private function mockScanImage($client): void
    {
        $scanImage = $this->createMock(ScanImage::class);
        $scanImage->method('getScanners')->willReturn(['test-scanner']);
        $scanImage->method('getScannerOptions')->willReturn(['resolutions' => [300, 600]]);

        $client->getContainer()->set(ScanImage::class, $scanImage);
    }
}
