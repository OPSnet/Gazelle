<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase {
    public function testDebugGeneral(): void {
        global $Debug;
        $this->assertCount(6, $Debug->perfInfo(), 'debug-perf-info');
        $this->assertGreaterThan(0.0, $Debug->cpuElapsed(), 'debug-cpu-elapsed');
        $this->assertGreaterThan(0, $Debug->epochStart(), 'debug-epoch-start');
        $this->assertGreaterThan(350, count($Debug->includeList()), 'debug-include-list');
        $this->assertTrue(\GazelleUnitTest\Helper::recentDate(date('Y-m-d H:i:s', (int)$Debug->epochStart()), 180), 'debug-recent-start');
    }

    public function testCreate(): void {
        $manager = new Manager\ErrorLog();
        $uri     = '/phpunit.php';
        $id      = $manager->create(
            uri:       $uri,
            userId:    0,
            duration:  0.0,
            memory:    0,
            nrQuery:   0,
            nrCache:   0,
            digest:    randomString(10),
            trace:     "a\nb",
            request:   json_encode([]), /** @phpstan-ignore-line result is not false */
            errorList: json_encode([]), /** @phpstan-ignore-line result is not false */
            loggedVar: json_encode([]), /** @phpstan-ignore-line result is not false */
        );

        $this->assertGreaterThan(0, $id, 'errorlog-create');
        $case = $manager->findById($id);
        $this->assertInstanceOf(ErrorLog::class, $case, 'errorlog-find');
        $this->assertEquals($uri, $case->uri(), 'errorlog-uri');
        $this->assertEquals([], $case->request(), 'errorlog-request');
        $this->assertEquals([], $case->errorList(), 'errorlog-errrolist');
        $this->assertEquals(["a", "b"], $case->trace(), 'errorlog-trace');

        $this->assertEquals(1, $case->remove(), 'errorlog-remove');
    }

    public function testCase(): void {
        $manager = new Manager\ErrorLog();
        global $Debug;
        $id = $Debug->saveCase('phpunit-case-1');
        $case = $manager->findById($id);
        $trace = $case->trace();

        $this->assertCount(1, $trace, 'debug-case-nr-trace');
        $this->assertEquals('phpunit-case-1', $trace[0], 'debug-case-trace');
        // if the next assertion fails, try uncommenting the next line to reset
        // $case->remove(); return;
        $this->assertEquals(1, $case->seen(), 'debug-case-seen');
        $this->assertEquals('cli', $case->uri(), 'debug-case-uri');
        $this->assertEquals(0, $case->userId(), 'debug-case-user-id');
        $this->assertTrue(\GazelleUnitTest\Helper::recentDate($case->created()), 'debug-case-created');
        $this->assertTrue(\GazelleUnitTest\Helper::recentDate($case->updated()), 'debug-case-updated');
        $case->remove();
    }

    public function testMark(): void {
        global $Debug;
        $event = 'phpunit-' . randomString();
        $Debug->mark($event);
        $list = $Debug->markList();
        $this->assertCount(1, $list, 'debug-marklist');
        $this->assertCount(4, $list[0], 'debug-mark-total');
        $this->assertEquals($event, $list[0][0], 'debug-mark-event');
    }
}
