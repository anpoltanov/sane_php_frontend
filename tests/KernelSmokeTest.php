<?php

declare(strict_types=1);

namespace App\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelSmokeTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();

        $this->assertSame('test', self::$kernel->getEnvironment());
        $this->assertNotNull(self::$kernel->getContainer());
    }

    public function testImagickExtensionIsAvailableInDocker(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('imagick is provided by the Docker image, not required on the host.');
        }

        $this->assertTrue(class_exists(\Imagick::class));
    }
}
