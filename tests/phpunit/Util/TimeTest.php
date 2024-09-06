<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Gazelle\Util\Time;

class TimeTest extends TestCase {
    public function testInvalidTimeAgo(): void {
        $epoch = time();
        $this->assertEquals(
            false,
            Time::timeAgo('not a valid timestamp'),
            "time-ago-invalid",
        );
    }

    public function testTimestampTimeAgo(): void {
        $epoch = time();
        $this->assertEquals(
            $epoch - strtotime('2000-01-01 01:01:01'),
            Time::timeAgo('2000-01-01 01:01:01'),
            "time-ago-timestamp",
        );
    }

    public static function providerTimestamp(): array {
        return [
            // delta                expected
            // -----                --------
            ['5',                   5     ],
            [1413,                  1413  ],
            [54321.0,               54321 ],
        ];
    }

    #[DataProvider('providerTimestamp')]
    public function testTimeAgo(string|int|float $delta, int $expected): void {
        $epoch = time();
        $this->assertEquals(
            $expected,
            Time::timeAgo($epoch - (is_float($delta) ? $delta : (int)$delta)),
            "time-ago-valid-$delta",
        );
    }

    public static function providerHours(): array {
        return [
            [      0,  2, 'Never'],
            [     -1,  2, 'Never'],
            [      1,  2, '1h'],
            [     24,  2, '1d'],
            [     26,  2, '1d2h'],
            [   3751,  1, '5mo'],
            [   3751,  2, '5mo4d'],
            [   3751,  3, '5mo4d5h'],
            [   3751,  4, '5mo4d5h'],
            [2343542,  5, '267y6mo1w3d2h'],
            [2000000, 30, '228y3mo3w1d2h']
        ];
    }

    #[DataProvider('providerHours')]
    public function testConvertHours(int $hours, int $levels, string $expected): void {
        $this->assertEquals(
            $expected,
            Time::convertHours($hours, $levels, false),
            "time-hours-$levels-$hours"
        );
    }

    public function testConvertHoursSpan(): void {
        $this->assertEquals(
            '<span>228y3mo3w1d2h</span>',
            Time::convertHours(2000000, 5),
            'time-convert-span'
        );
    }
}
