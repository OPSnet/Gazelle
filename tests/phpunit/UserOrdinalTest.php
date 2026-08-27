<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class UserOrdinalTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('ord.' . randomString(10), 'ord');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testOrdinal(): void {
        $name = 'request-bounty-vote';

        $this->assertSame(
            $this->user->ordinal()->defaultValue($name),
            $this->user->ordinal()->value($name),
            'ordinal-is-default',
        );
        $this->assertSame(1, $this->user->ordinal()->set($name, 200), 'ordinal-set');
        $this->assertSame(200, $this->user->ordinal()->value($name), 'ordinal-value');

        $this->assertSame(2, $this->user->ordinal()->increment($name, 300), 'ordinal-increment');
        $this->assertSame(500, $this->user->ordinal()->value($name), 'ordinal-new-value');

        $this->assertSame(
            1,
            $this->user->ordinal()->removeOrdinal($name),
            'ordinal-remove',
        );
        $this->assertSame(
            $this->user->ordinal()->defaultValue($name),
            $this->user->ordinal()->value($name),
            'ordinal-again-default',
        );
        $this->assertSame(
            0,
            $this->user->ordinal()->set(
                $name,
                $this->user->ordinal()->defaultValue($name)
            ),
            'ordinal-set-default',
        );
    }

    public function testMissing(): void {
        $this->expectException(\UnexpectedValueException::class);
        $this->user->ordinal()->defaultValue('@nope@');
    }

    public function testSetInexistent(): void {
        $this->user->ordinal();
        $this->assertSame(
            0,
            $this->user->ordinal()->set('@nope@', 123),
            'ordinal-set-inexistent',
        );
    }
}
