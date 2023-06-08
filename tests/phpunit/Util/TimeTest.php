<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase {
    /**
     * @group no-ci
     * These tests cause spurious errors when test execution takes too long.
     *
     * @dataProvider providerTimestamp
     * @param $timestamp
     * @param $expected
     */
    function testTimeAgo($timestamp, $expected): void {
        $this->assertEquals($expected, Time::timeAgo($timestamp));
    }

    function providerTimestamp(): array {
        return [
            ['not a valid timestamp', false],
            ['5', time() - 5],
            [1413, time() - 1413],
            ['2000-01-01 01:01:01', time() - strtotime('2000-01-01 01:01:01')]
        ];
    }

    /**
     * @param $hours
     * @param $expected
     *
     * @dataProvider providerHours
     */
    function testConvertHours($hours, $levels, $expected): void {
        $this->assertEquals($expected, Time::convertHours($hours, $levels, false));
    }

    function providerHours(): array {
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

    function testConvertHoursSpan(): void {
        $this->assertEquals('<span>228y3mo3w1d2h</span>', Time::convertHours(2000000, 5));
    }
}
