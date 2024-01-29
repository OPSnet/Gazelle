<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

class TimeTest extends TestCase {
    // These tests cause spurious errors when test execution takes too long.
    #[Group('no-ci')]
    #[DataProvider('providerTimestamp')]
    public function testTimeAgo(string $timestamp, mixed $expected): void {
        $this->assertEquals($expected, Time::timeAgo($timestamp));
    }

    public static function providerTimestamp(): array {
        return [
            ['not a valid timestamp', false],
            ['5', time() - 5],
            [1413, time() - 1413],
            ['2000-01-01 01:01:01', time() - strtotime('2000-01-01 01:01:01')]
        ];
    }

    #[DataProvider('providerHours')]
    public function testConvertHours(int $hours, int $levels, string $expected): void {
        $this->assertEquals($expected, Time::convertHours($hours, $levels, false));
    }

    public static function providerHours(): array {
        return [
            [0, 2, 'Never'],
            [-1, 2, 'Never'],
            [1, 2, '1h'],
            [24, 2, '1d'],
            [26, 2, '1d2h'],
            [3750, 1, '5mo'],
            [3750, 2, '5mo4d'],
            [3750, 3, '5mo4d4h'],
            [2343542, 5, '267y6mo1w3d2h'],
            [2000000, 30, '228y3mo3w1d2h']
        ];
    }

    public function testConvertHoursSpan(): void {
        $this->assertEquals('<span>228y3mo3w1d2h</span>', Time::convertHours(2000000, 5));
    }
}
