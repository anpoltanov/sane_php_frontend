<?php

declare(strict_types=1);

namespace App\Test;

use App\Service\ScanImage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ScannerApiTest extends WebTestCase
{
    public function testScannerIndexReturnsJsonArrayNotDoubleEncoded(): void
    {
        $client = static::createClient();

        $scanImage = $this->createMock(ScanImage::class);
        $scanImage->method('getScanners')->willReturn(['net:epson']);
        $client->getContainer()->set(ScanImage::class, $scanImage);

        $client->request('GET', '/scanner');

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(['net:epson'], $data);
    }
}
