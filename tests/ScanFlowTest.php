<?php

declare(strict_types=1);

namespace App\Test;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ScanFlowTest extends WebTestCase
{
    public function testScanPreviewAndCyrillicPdfDownload(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Scan')->form([
            'scan[fileName]' => 'скан',
            'scan[resolution]' => '300',
        ]);
        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('scanId', $payload);

        $client->request('GET', $payload['previewUrl']);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'image/jpeg');

        $client->request('GET', sprintf(
            '/scan/%s/download?format=pdf&filename=%s',
            $payload['scanId'],
            rawurlencode('скан')
        ));
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/pdf');

        $disposition = (string) $client->getResponse()->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('filename*=utf-8\'\'', $disposition);
        $this->assertStringContainsString(rawurlencode('скан.pdf'), $disposition);
    }

    public function testScannerOptionsAcceptsColonInDeviceName(): void
    {
        $client = static::createClient();
        $client->request('GET', '/scanner/options?device='.rawurlencode('mock:scanner'));

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame([150, 300, 600], $data['resolutions']);
    }
}
