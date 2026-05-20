<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class ErrorTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('error.' . randomString(6), 'error');
        Base::staticRequestContext()->setViewer($this->user);
        Base::staticRequestContext()->setSessionId('phpunit');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testOutputError400(): void {
        $this->expectOutputRegex("#<title>Error 400 ⠶ " . SITE_NAME . "</title>#");
        echo Error400::payload('');
    }

    public function testOutputErrorDetails(): void {
        $detail = randomString();
        $this->expectOutputRegex("/Additional details: $detail/");
        echo Error400::payload($detail);
    }

    public function testOutputError403(): void {
        $this->expectOutputRegex("#<title>Error 403 ⠶ " . SITE_NAME . "</title>#");
        echo Error403::payload('');
    }

    public function testOutputError404(): void {
        $this->expectOutputRegex("#<title>Error 404 ⠶ " . SITE_NAME . "</title>#");
        echo Error404::payload('');
    }

    public function testOutputError429(): void {
        $this->expectOutputRegex("#<title>Error 429 ⠶ " . SITE_NAME . "</title>#");
        echo Error429::payload('');
    }

    public function testOutputError500(): void {
        $this->expectOutputRegex("#<title>Error 500 ⠶ " . SITE_NAME . "</title>#");
        echo Error500::payload('');
    }
}
