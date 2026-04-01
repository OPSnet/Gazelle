<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class SessionCookieTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('cookie.' . randomString(6), 'cookie');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testValidSessionCookie(): void {
        $sessionKey = randomString();
        $payload = SessionCookie::encode($this->user, $sessionKey);
        $this->assertGreaterThan(40, strlen($payload), 'cookie-payload-length');

        $cookie = new SessionCookie($payload);
        $this->assertTrue($cookie->isValid(), 'cookie-session-valid');
        $this->assertEquals($sessionKey, $cookie->sessionKey(), 'cookie-session-key');
        $this->assertEquals($this->user->id, $cookie->userId(), 'cookie-userid');

        $new = new SessionCookie($cookie->payload());
        $this->assertEquals($this->user->id, $new->userId(), 'cookie-regenerate');
    }

    public function testLoginCookie(): void {
        $session = $this->user->session()->create([
            'keep-logged' => '0',
            'browser'     => [
               'Browser'                => 'phpunit-browser',
               'BrowserVersion'         => '1.1',
               'OperatingSystem'        => 'phpunit-os',
               'OperatingSystemVersion' => '2.2',
            ],
            'ipaddr'    => '127.0.0.1',
            'useragent' => 'phpunit-browser',
        ]);
        $cookie = new SessionCookie(
            SessionCookie::encode(
                $this->user,
                $session['SessionID'],
            )
        );
        $this->assertEquals(
            $this->user->id,
            new Manager\User()->findByCookie($cookie)->id,
            'cookie-find-user'
        );
    }

    public function testGarbageSessionCookie(): void {
        $cookie = new SessionCookie('');
        $this->assertFalse($cookie->isValid(), 'cookie-session-invalid');
    }

    public function testCookieEmit(): void {
        header_remove(null);
        $sessionKey = 'phpunit';
        $cookie = new SessionCookie(SessionCookie::encode($this->user, $sessionKey));
        $cookie->emit(time() + 3600);

        $headerList = xdebug_get_headers();
        $this->assertCount(1, $headerList, 'session-cookie-header');
        $header = current($headerList);
        $this->assertEquals(
            1,
            // Set-Cookie: session=e%2F8GAPo...;
            preg_match('/Set-Cookie: session=([^;]+);/', $header, $match),
            'session-cookie-well-formed'
        );
        $new = new SessionCookie(urldecode($match[1]));
        $this->assertEquals(
            $sessionKey,
            $new->sessionKey(),
            'session-cookie-decoded-key',
        );
        $this->assertEquals(
            $this->user->id,
            $new->userId(),
            'session-cookie-decoded-user',
        );
    }
}
