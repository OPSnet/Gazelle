<?php

namespace Gazelle;

use Gazelle\Util\ImageProxy;
use PHPUnit\Framework\TestCase;

class ImageProxyTest extends TestCase {
    public function testBadHost(): void {
        $this->assertNull(ImageProxy::badHost("https://some.image.host.example.com/image.jpg"), 'imageproxy-badhost-good');
        $this->assertEquals('phpunit.bad', ImageProxy::badHost("https://phpunit.bad/image.jpg"), 'imageproxy-badhost-bad');
    }
}
