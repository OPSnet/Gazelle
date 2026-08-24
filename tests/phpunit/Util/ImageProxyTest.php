<?php

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Util\ImageProxy;
use PHPUnit\Framework\TestCase;

class ImageProxyTest extends TestCase {
    public function testBadHost(): void {
        $this->assertNull(
            ImageProxy::badHost('https://some.image.host.example.com/image.jpg'),
            'imageproxy-badhost-good',
        );
        $this->assertSame(
            'phpunit.bad', // depends on existence in config or override
            ImageProxy::badHost('https://phpunit.bad/image.jpg'),
            'imageproxy-badhost-bad',
        );
    }
}
