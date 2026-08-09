<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use GazelleUnitTest\Helper;

class UserAdvancedTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('usearch.' . randomString(10), 'usersearch');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testSearchUserFuzzy(): void {
        $fuzzy = new Search\User('');
        $this->assertSame(
            "f ~~* concat('%', ?::text, '%')",
            $fuzzy->matchField('f'),
            'usearch-fuzzy-match-field',
        );
        $this->assertSame(
            "f ~~* concat(?::text, '%')",
            $fuzzy->leftMatch('f'),
            'usearch-fuzzy-match-left',
        );
    }

    public function testSearchUserRegexp(): void {
        $regexp = new Search\User('regexp');
        $this->assertSame(
            "f ~* ?",
            $regexp->matchField('f'),
            'usearch-regexp-match-field',
        );
        $this->assertSame(
            // FIXME: should be ^ anchored
            "f ~* ?",
            $regexp->leftMatch('f'),
            'usearch-regexp-match-left',
        );
    }

    public function testSearchUserStrict(): void {
        $strict = new Search\User('strict');
        $this->assertSame(
            "f = ?",
            $strict->matchField('f'),
            'usearch-strict-match-field',
        );
        $this->assertSame(
            // FIXME: should be left substring
            "f = ?",
            $strict->leftMatch('f'),
            'usearch-strict-match-left',
        );
    }

    #[DataProvider('dataDate')]
    public function testSearchUserDate(string $name, string $compare, string $expected): void {
        $this->assertSame(
            $expected,
            new Search\User('')->date('f', $compare),
            $name,
        );
    }

    public static function dataDate(): array {
        return [
            ['usearch-date-after',   'after',   "f > ? + '1 DAY'::interval"],
            ['usearch-date-before',  'before',  "f < ?"],
            ['usearch-date-between', 'between', "f BETWEEN ? AND ? + '1 DAY'::interval"],
            ['usearch-date-default', 'default', "f >= ? AND f < ? + '1 DAY'::interval"],
        ];
    }

    #[DataProvider('dataOp')]
    public function testOp(string $name, string $compare, string $expected): void {
        $this->assertSame(
            $expected,
            new Search\User('')->op('f', $compare),
            $name,
        );
    }

    public static function dataOp(): array {
        return [
            ['usearch-op-equal',      'equal',     'f = ?'],
            ['usearch-op-above',      'above',     'f > ?'],
            ['usearch-op-below',      'below',     'f < ?'],
            ['usearch-op-between',    'between',   'f BETWEEN ? AND ?'],
            ['usearch-op-isnotnull',  'isnotnull', 'f IS NOT NULL'],
            ['usearch-op-isnull',     'isnull',    'f IS NULL'],
            ['usearch-op-no',         'no',        'f != ?'],
            ['usearch-op-not_equal',  'not_equal', 'f != ?'],
        ];
    }
}
