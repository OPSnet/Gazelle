<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Gazelle\Util\Time;

class TimeTest extends TestCase {
    public function testInvalidTimeAgo(): void {
        time();
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

    public static function providerSeconds(): array {
        $hour = 3600;
        $day  = $hour * 24;
        $week = $day  *  7;
        $year = $week * 52;
        return [
            [               -1, '0s'],
            [                0, '0s'],
            [                1, '1s'],
            [                1, '1s'],
            [               60, '1m'],
            [              119, '1m59s'],
            [             3599, '59m59s'],
            [       $hour * 19, '19h'],
            [       $hour + 62, '1h1m'],
            [         $day * 4, '4d'],
            [       $week + 61, '1w1m'],
            [$year + $hour * 3, '1y3h'],
        ];
    }

    #[DataProvider('providerSeconds')]
    public function testConvertSeconds(int $seconds, string $expected): void {
        $this->assertEquals(
            $expected,
            Time::convertSeconds($seconds),
            "time-seconds-$seconds"
        );
    }

    public static function providerDiff(): array {
        return [
            [ null, 'null', 'Never'],
            [ false, 'false', 'Never'],
            [ strtotime('garbage'), 'garbage', 'Never'],
        ];
    }

    #[DataProvider('providerDiff')]
    public function testDiff(int|false|null|\DateTimeImmutable $time, string $label, string $expected, int $levels = 2): void {
        $this->assertEquals(
            $expected,
            Time::diff($time, $levels, false),
            "time-seconds-$label"
        );
    }

    public function testImmutableDiff(): void {
        $this->assertEquals(
            '3 days ago',
            Time::diff(new \DateTimeImmutable('3 days ago'), 2, false),
            "time-immutable-3d"
        );
    }

    public static function providerRealtimeDiff(): array {
        $hour = 3600;
        $day  = $hour * 24;
        $week = $day  *  7;
        $year = $week * 52;
        return [
            [ 90, 'm1', '1 minute ago'],
            [ 120, 'm2', '2 minutes ago'],
            [ $hour + 90, 'h1m1', '1 hour and 1 minute ago'],
            [ 2 * $hour, 'h2', '2 hours ago'],
            [ $day + $hour + 90, 'd1h1m1', '1 day, 1 hour and 1 minute ago', 3],
            [ $year * 2 + $week * 10, 'y2w10', '2 years, 2 months ago'],
        ];
    }

    #[DataProvider('providerRealtimeDiff')]
    public function testRealtimeDiff(int $diff, string $label, string $expected, int $levels = 2): void {
        $this->assertEquals(
            $expected,
            Time::diff(time() - $diff, $levels, false),
            "time-seconds-$label"
        );
    }
}
