<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class RequestContextTest extends TestCase {
    protected User $user;

    public function tearDown(): void {
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function testRequestContext(): void {
        $context = new RequestContext(
            '/phpunit.php',
            '224.0.0.1',
            'Lidarr/3.5.8 (windows 98)',
        );
        $this->assertTrue($context->isValid(), 'context-is-valid');
        $this->assertSame('phpunit', $context->module(), 'context-module');
        $this->assertSame('224.0.0.1', $context->remoteAddr(), 'context-remote-addr');
        $this->assertSame('Lidarr', $context->browser(), 'context-browser');
        $this->assertSame('3.5', $context->browserVersion(), 'context-version-browser');
        $this->assertSame('98', $context->osVersion(), 'context-version-os');
        $this->assertSame('Windows', $context->os(), 'context-os');

        $module = randomString(4);
        $context->setModule($module);
        $this->assertSame($module, $context->module(), 'context-override-module');

        $context->anonymize();
        $this->assertSame('127.0.0.1', $context->remoteAddr(), 'context-override-remoteaddr');
        $this->assertSame('staff-browser', $context->browser(), 'context-override-browser');
        $this->assertSame('', $context->os(), 'context-override-os');
    }

    public function testBadRequest(): void {
        $context = new RequestContext('', '', '');
        $this->assertFalse($context->isValid(), 'context-not-valid');
        $this->assertSame('', $context->browser(), 'context-invalid-browser');
    }

    // Any object that derives from Base has access to the request context
    public function testObject(): void {
        Base::setRequestContext(
            new RequestContext(
                '/phpunit.php',
                '225.0.0.1',
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.3',
            )
        );
        $this->assertSame(
            '225.0.0.1',
            new Manager\TGroup()->requestContext()->remoteAddr(),
            'context-manager-ip',
        );
        $this->user = Helper::makeUser('base.' . randomString(6), 'base object');
        $this->assertSame(
            'Chrome',
            $this->user->requestContext()->browser(),
            'context-user-browser',
        );
        $this->assertSame(
            '125',
            $this->user->requestContext()->browserVersion(),
            'context-user-browser-version',
        );
        $this->assertSame(
            'Windows',
            $this->user->requestContext()->os(),
            'context-user-os',
        );
        $this->assertSame(
            '10',
            $this->user->requestContext()->osVersion(),
            'context-user-os-version',
        );
    }
}
