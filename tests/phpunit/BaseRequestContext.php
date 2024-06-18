<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class BaseRequestContext extends TestCase {
    protected \Gazelle\User $user;

    public function tearDown(): void {
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function testBaseRequestContext(): void {
        $context = new Gazelle\BaseRequestContext(
            '/phpunit.php',
            '224.0.0.1',
            'Lidarr/3.5.8 (windows 98)',
        );
        $this->assertTrue($context->isValid(), 'context-is-valid');
        $this->assertEquals('phpunit', $context->module(), 'context-module');
        $this->assertEquals('224.0.0.1', $context->remoteAddr(), 'context-remote-addr');
        $this->assertEquals('Lidarr', $context->browser(), 'context-browser');
        $this->assertEquals('3.5', $context->browserVersion(), 'context-version-browser');
        $this->assertEquals('98', $context->osVersion(), 'context-version-os');
        $this->assertEquals('Windows', $context->os(), 'context-os');

        $module = randomString(4);
        $context->setModule($module);
        $this->assertEquals($module, $context->module(), 'context-override-module');

        $context->anonymize();
        $this->assertEquals('127.0.0.1', $context->remoteAddr(), 'context-override-remoteaddr');
        $this->assertEquals('staff-browser', $context->browser(), 'context-override-browser');
        $this->assertNull($context->os(), 'context-override-os');
    }

    public function testBadRequest(): void {
        $context = new Gazelle\BaseRequestContext('', '', '');
        $this->assertFalse($context->isValid(), 'context-not-valid');
        $this->assertNull($context->browser(), 'context-invalid-browser');
    }

    // Any object that derives from Base has access to the request context
    public function testObject(): void {
        Gazelle\Base::setRequestContext(
            new Gazelle\BaseRequestContext(
                '/phpunit.php',
                '225.0.0.1',
                'Lidarr/5.8.13 (windows 98)',
            )
        );
        $this->assertEquals(
            '225.0.0.1',
            (new Gazelle\Manager\TGroup())->requestContext()->remoteAddr(),
            'context-manager-ip',
        );
        $this->user = Helper::makeUser('base.' . randomString(6), 'base object');
        $this->assertEquals(
            'Lidarr',
            $this->user->requestContext()->browser(),
            'context-user-browser',
        );
    }
}
