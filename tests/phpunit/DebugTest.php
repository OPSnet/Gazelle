<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use GazelleUnitTest\Helper;

class DebugTest extends TestCase {
    public function testDebugGeneral(): void {
        global $Debug;
        $this->assertCount(6, $Debug->perfInfo(), 'debug-perf-info');
        $this->assertGreaterThan(0.0, $Debug->cpuElapsed(), 'debug-cpu-elapsed');
        $this->assertGreaterThan(0, $Debug->epochStart(), 'debug-epoch-start');
        $this->assertGreaterThan(350, count($Debug->includeList()), 'debug-include-list');
        $this->assertTrue(Helper::recentDate(date('Y-m-d H:i:s', (int)$Debug->epochStart()), 180), 'debug-recent-start');

        $this->assertCount(70, $Debug->durationHistogramKeyList(), 'debug-duration-histo-key-list');
        $this->assertTrue($Debug->initDurationHistogram(), 'debug-duration-init');
        $initial = $Debug->durationHistogram();
        $this->assertCount(70, $initial, 'debug-duration-histo-initial');
        $this->assertEquals(60000, $Debug->storeDuration(59999999.0), 'debug-duration-store');
        $after = $Debug->durationHistogram();
        $this->assertEquals($initial['60s'] + 1, $after['60s'], 'debug-duration-inc');

        $this->assertCount(128, $Debug->memoryHistogramKeyList(), 'debug-memory-histo-key-list');
        $this->assertTrue($Debug->initMemoryHistogram(), 'debug-memory-init');
        $initial = $Debug->memoryHistogram();
        $this->assertCount(128, $initial, 'debug-memory-histo-initial');
        $this->assertEquals(128, $Debug->storeMemory(134217728), 'debug-memory-store');
        $after = $Debug->memoryHistogram();
        $this->assertEquals($initial['128MiB'] + 1, $after['128MiB'], 'debug-memory-inc');
    }

    public static function providerDurationRound(): array {
        return [
            ['debug-neg-dur',         50,       -1.0],
            ['debug-0-dur',           50,        0.0],
            ['debug-0.1-dur',         50,        0.1],
            ['debug-1-dur',           50,        1.0],
            ['debug-10-dur',          50,       10.0],
            ['debug-100-dur',         50,      100.0],
            ['debug-1000-dur',        50,     1000.0],
            ['debug-10000-dur',       50,    10000.0],
            ['debug-49999-dur',       50,    49999.99999999],
            ['debug-50000-dur',       50,    50000.0],
            ['debug-50001-dur',      100,    50000.00000000001],
            ['debug-99999-dur',      100,    99999.99999999999],
            ['debug-100000-dur',     100,   100000.0],
            ['debug-100001-dur',     200,   100000.00000000001],
            ['debug-899999-dur',     900,   899999.99999999999],
            ['debug-999999-dur',    1000,   999999.99999999999],
            ['debug-1000000-dur',   1000,  1000000.00000000000],
            ['debug-1000001-dur',   2000,  1000000.0000000001],
            ['debug-1999999-dur',   2000,  1999999.9999999999],
            ['debug-19999999-dur', 20000, 19999999.9999999999],
        ];
    }

    #[DataProvider('providerDurationRound')]
    public function testDebugDurationRound(string $name, int $rounded, float $input): void {
        global $Debug;
        $this->assertEquals($rounded, $Debug->durationRound($input), $name);
    }

    public static function providerMemoryRound(): array {
        return [
            ['debug-0-mem',     0,        0],
            ['debug-1M--mem',   0,  1048575],
            ['debug-1M-mem',    1,  1048576],
            ['debug-1M+-mem',   1,  1048577],
            ['debug-64M--mem', 63, 67108863],
            ['debug-64M-mem',  64, 67108864],
            ['debug-64M+-mem', 64, 67108865],
        ];
    }

    #[DataProvider('providerMemoryRound')]
    public function testDebugMemoryRound(string $name, int $rounded, float $input): void {
        global $Debug;
        $this->assertEquals($rounded, $Debug->memoryRound($input), $name);
    }

    public function testCreate(): void {
        $manager  = new Manager\ErrorLog();
        $uri      = '/phpunit.php';
        $errorLog = $manager->create(
            uri:       $uri,
            userId:    null,
            duration:  0.0,
            memory:    0,
            nrQuery:   0,
            nrCache:   0,
            trace:     "a\nb",
            request:   [],
            errorList: [],
        );

        $this->assertGreaterThan(0, $errorLog->id, 'errorlog-create');
        $case = $manager->findById($errorLog->id);
        $this->assertInstanceOf(ErrorLog::class, $case, 'errorlog-find');
        $this->assertEquals($uri, $case->uri(), 'errorlog-uri');
        $this->assertEquals([], $case->request(), 'errorlog-request');
        $this->assertEquals([], $case->errorList(), 'errorlog-errrolist');
        $this->assertEquals(["a", "b"], $case->trace(), 'errorlog-trace');
        $this->assertEquals(
            $case->id,
            $manager->findByDigest("a\nb", [])?->id,
            'errorlog-find-by-digest',
        );

        $this->assertEquals(1, $case->remove(), 'errorlog-remove');
    }

    public function testDebugCase(): void {
        global $Cache;
        $key = 'phpunit_' . randomString();
        $Cache->cache_value($key, 'phpunit', 60);
        $Cache->get_value($key);
        DB::DB()->scalar('select now()');

        global $Debug;
        $case = $Debug->saveCase('phpunit-case-1');
        $this->assertGreaterThan(0, $case->id, 'php-case-is-saved');

        $trace = $case->trace();
        $this->assertCount(1, $trace, 'debug-case-nr-trace');
        $this->assertEquals('phpunit-case-1', $trace[0], 'debug-case-trace');
        // if the next assertion fails, try uncommenting the next line to reset
        // $case->remove(); return;
        $this->assertEquals(1, $case->seen(), 'debug-case-seen');
        $this->assertStringEndsWith('vendor/bin/phpunit', $case->uri(), 'debug-case-uri');
        $this->assertEquals(0, $case->userId(), 'debug-case-user-id');
        $this->assertTrue(Helper::recentDate($case->created()), 'debug-case-created');
        $this->assertTrue(Helper::recentDate($case->updated()), 'debug-case-updated');
        $this->assertGreaterThan(0.0, $case->duration(), 'debug-case-duration');
        $this->assertGreaterThan(1000000, $case->memory(), 'debug-case-memory');
        $this->assertGreaterThan(0, $case->nrCache(), 'debug-case-nr-cache');
        $this->assertGreaterThan(0, $case->nrQuery(), 'debug-case-nr-query');
        $this->assertStringContainsString(
            "Case #{$case->id}",
            $case->link(),
            'debug-case-link',
        );
        $this->assertStringContainsString(
            "case={$case->id}",
            $case->location(),
            'debug-case-location',
        );
        $case->remove();
    }

    public function testDebugError(): void {
        $this->expectException(\DivisionByZeroError::class);
        try {
            $fail = 1 / 0; /** @phpstan-ignore-line binaryOp.invalid */
        } catch (\DivisionByZeroError $e) {
            global $Debug;
            $case = $Debug->saveError($e);
            $this->assertStringEndsWith('vendor/bin/phpunit', $case->uri(), 'debug-error-case-uri');
            $this->assertEquals('Division by zero', $case->trace()[0], 'debug-error-case-trace');
            $case->remove();
            // keep phpunit happy
            throw $e;
        }
    }

    public function testMark(): void {
        global $Debug;
        $message = 'phpunit-' . randomString();
        $Debug->mark($message);
        $list = array_filter($Debug->markList(), fn ($m) => $m[0] === $message);
        $this->assertCount(1, $list, 'debug-marklist');
        $event = current($list);
        $this->assertCount(4, $event, 'debug-mark-total');
        $this->assertEquals($message, $event[0], 'debug-mark-event');
    }
}
