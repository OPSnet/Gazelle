<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class UserOrdinalTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('ord.' . randomString(10), 'ord');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testOrdinal(): void {
        $name = 'request-bounty-vote';

        $this->assertEquals(
            $this->user->ordinal()->defaultValue($name),
            $this->user->ordinal()->value($name),
            'ordinal-is-default',
        );
        $this->assertEquals(1, $this->user->ordinal()->set($name, 200), 'ordinal-set');
        $this->assertEquals(200, $this->user->ordinal()->value($name), 'ordinal-value');

        $this->assertEquals(2, $this->user->ordinal()->increment($name, 300), 'ordinal-increment');
        $this->assertEquals(500, $this->user->ordinal()->value($name), 'ordinal-new-value');

        $this->assertEquals(1, $this->user->ordinal()->remove($name), 'ordinal-remove');
        $this->assertEquals(
            $this->user->ordinal()->defaultValue($name),
            $this->user->ordinal()->value($name),
            'ordinal-again-default',
        );
    }

    public function testMissing(): void {
        $ordinal = $this->user->ordinal();
        $this->expectException(\TypeError::class);
        $this->user->ordinal()->defaultValue('@nope@');
    }

    public function testSetInexistent(): void {
        $ordinal = $this->user->ordinal();
        $this->assertEquals(
            0,
            $this->user->ordinal()->set('@nope@', 123),
            'ordinal-set-inexistent',
        );
    }
}
