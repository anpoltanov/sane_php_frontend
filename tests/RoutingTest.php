<?php

declare(strict_types=1);

namespace App\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

class RoutingTest extends KernelTestCase
{
    public function testRequiredRoutesAreRegistered(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');
        $collection = $router->getRouteCollection();

        $this->assertNotNull($collection->get('scan'));
        $this->assertNotNull($collection->get('scanner_index'));
        $this->assertNotNull($collection->get('scanner_options'));
        $this->assertSame('/', $collection->get('scan')->getPath());
        $this->assertSame('/scanner', $collection->get('scanner_index')->getPath());
        $this->assertSame('/scanner/options', $collection->get('scanner_options')->getPath());
    }
}
