<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use GazelleUnitTest\Helper;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
ini_set('memory_limit', '1G');
// phpcs:enable

class SchedulerTest extends TestCase {
    public function testGlobalRun(): void {
        $scheduler = new TaskScheduler();
        $this->expectOutputRegex('/^(?:\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[(?:debug|info)\] (.*?)\n|Running task (?:.*?)\.\.\.DONE! \(\d+\.\d+\)\n)*$/');
        $scheduler->run();
    }

    public function testRunWithMissingImplementation(): void {
        $scheduler = new TaskScheduler();
        $name = "RunUnimplemented";
        $db = DB::DB();
        $db->prepared_query("
            DELETE FROM periodic_task WHERE classname = ?
            ", $name
        );
        $db->prepared_query("
            INSERT INTO periodic_task
                   (classname, name, description, period)
            VALUES (?,         ?,    ?,           86400)
            ",
            $name, "phpunit run task", "A run with no PHP implementation"
        );

        $this->expectOutputRegex('/^(?:\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[(?:debug|info)\] (.*?)\n|Running task (?:.*?)\.\.\.DONE! \(\d+\.\d+\)\n)*$/');
        $scheduler->run();
        $db->prepared_query("
            DELETE FROM periodic_task WHERE classname = ?
            ", $name
        );
    }

    public function testMissingTaskEntry(): void {
        $scheduler = new TaskScheduler();
        $this->assertEquals(
            -1,
            $scheduler->runTask(-666),
            "sched-task-no-such-task-id"
        );
        $this->assertEquals(
            -1,
            $scheduler->runClass("NoSuchClassname"),
            "sched-task-no-such-class"
        );
        $this->assertFalse(
            $scheduler->isClassValid("NoSuchClassname"),
            "sched-is-class-valid"
        );
    }

    public function testMissingImplementation(): void {
        $scheduler = new TaskScheduler();
        $name = "Unimplemented";
        $db = DB::DB();
        $db->prepared_query("
            DELETE FROM periodic_task WHERE classname = ?
            ", $name
        );
        $db->prepared_query("
            INSERT INTO periodic_task
                   (classname, name, description, period)
            VALUES (?,         ?,    ?,           86400)
            ",
            $name, "phpunit task", "A task with no PHP implementation"
        );
        ob_start();
        $this->assertEquals(
            -1,
            $scheduler->runClass($name),
            "sched-task-unimplemented"
        );
        ob_end_clean();
        $db->prepared_query("
            DELETE FROM periodic_task WHERE classname = ?
            ", $name
        );
    }

    #[DataProvider('taskProvider')]
    public function testTask(string $taskName): void {
        $scheduler = new TaskScheduler();
        $this->assertTrue(
            $scheduler->isClassValid($taskName),
            "sched-has-task-$taskName"
        );
        ob_start();
        $this->assertGreaterThanOrEqual(
            -1,
            $scheduler->runClass($taskName),
            "sched-task-$taskName"
        );
        ob_end_clean();
    }

    public static function taskProvider(): array {
        return [
            ['ArtistUsage'],
            ['BetterTranscode'],
            ['CalculateContestLeaderboard'],
            ['CommunityStats'],
            ['CycleAuthKeys'],
            ['DeleteTags'],
            ['DisableDownloadingRatioWatch'],
            ['DisableLeechingRatioWatch'],
            ['DisableStuckTasks'],
            ['DisableUnconfirmedUsers'],
            ['Donations'],
            ['ExpireFlTokens'],
            ['ExpireInvites'],
            ['ExpireTagSnatchCache'],
            ['Freeleech'],
            ['HideOldRequests'],
            ['InactiveUserWarn'],
            ['InactiveUserDeactivate'],
            ['LockOldThreads'],
            ['LowerLoginAttempts'],
            ['Peerupdate'],
            ['PromoteUsers'],
            ['PurgeOldTaskHistory'],
            ['RatioRequirements'],
            ['RatioWatch'],
            ['RelayDatabase'],
            ['RemoveDeadSessions'],
            ['ResolveStaffPms'],
            ['SSLCertificate'],
            ['Test'],
            ['UpdateDailyTop10'],
            ['UpdateSeedTimes'],
            ['UpdateUserBonusPoints'],
            ['UpdateWeeklyTop10'],
            ['UserLastAccess'],
            ['UserStatsDaily'],
            ['UserStatsMonthly'],
            ['UserStatsYearly'],
        ];
    }

    public function testTaskDetailList(): void {
        $list = new TaskScheduler()->taskDetailList();
        $this->assertCount(42, $list, 'task-detail-list');
        $detail = current($list);
        $this->assertEquals(
            [
                "periodic_task_id", "name", "description", "period",
                "is_enabled", "is_sane", "run_now", "runs", "processed",
                "errors", "events", "duration", "status", "last_run",
                "next_run",
            ],
            array_keys($detail),
            'task-detail-entry'
        );
    }

    public function testTaskHistory(): void {
        $scheduler = new TaskScheduler();
        $task      = $scheduler->findByName('Test');
        $taskId    = $task['periodic_task_id'];
        $initial   = $scheduler->taskHistory($taskId, 10, 0);
        $total     = $scheduler->taskRunTotal($taskId);
        ob_start();
        $scheduler->runTask($taskId);
        ob_end_clean();
        $this->assertEquals(
            $total + 1,
            $scheduler->taskRunTotal($taskId),
            'task-run-total'
        );

        $history = $scheduler->taskHistory($taskId, 10, 0);
        $this->assertEquals(
            1,
            $history->count - $initial->count,
            'task-history-total'
        );
        $item = current($history->items);
        $this->assertTrue(Helper::recentDate($item->launchTime), 'task-launch-time');
        $this->assertEquals('completed', $item->status, 'task-status');
        $this->assertEquals(0, $item->nrErrors, 'task-nr-error');
        $this->assertEquals(0, $item->nrItems, 'task-nr-item');
    }

    public function testTaskEnqueue(): void {
        $scheduler = new TaskScheduler();
        $task      = $scheduler->findByName('Test');
        $taskId    = $task['periodic_task_id'];
        $this->assertEquals(
            1,
            $scheduler->enqueue($taskId),
            'task-enqueue',
        );
        $task = $scheduler->findByName('Test');
        $this->assertEquals(1, $task['run_now'], 'task-is-enqueued');
        $this->assertEquals(
            1,
            $scheduler->clear($taskId),
            'task-clear',
        );
        $this->assertEquals(
            1,
            $scheduler->runNow($taskId),
            'task-run-now',
        );
    }

    public function testTaskStats(): void {
        $scheduler = new TaskScheduler();
        $task      = $scheduler->findByName('Test');
        $taskId    = $task['periodic_task_id'];
        $stats     = $scheduler->taskRuntimeStats($taskId, 1);
        $this->assertCount(2, $stats, 'task-runtime-stats-count');
        $this->assertEquals(
            'duration', $stats[0]['name'], 'task-stats-duration',
        );
        $this->assertEquals(
            'processed', $stats[1]['name'], 'task-stats-processed',
        );
    }

    public function testGlobalStats(): void {
        $stats = new TaskScheduler()->runtimeStats();
        $this->assertEquals(
            ['hourly', 'daily', 'tasks', 'totals'],
            array_keys($stats),
            'task-stats-global',
        );
    }

    public function testTaskUpdate(): void {
        $this->assertEquals(
            0,
            new TaskScheduler()->updateTask(
                taskId:      1,
                name:        'Test',
                class:       'Bonkers',
                description: 'Scheduler functionality test ' . randomString(),
                period:      60,
                isEnabled:   false,
                isSane:      true,
                isDebug:     false,
            ),
            'task-update-bad-class',
        );
        $this->assertEquals(
            1,
            new TaskScheduler()->updateTask(
                taskId:      1,
                name:        'Test',
                class:       'Test',
                description: 'Scheduler functionality test ' . randomString(),
                period:      60,
                isEnabled:   false,
                isSane:      true,
                isDebug:     false,
            ),
            'task-update-test',
        );
        new TaskScheduler()->updateTask(
            taskId:      1,
            name:        'Test',
            class:       'Test',
            description: 'Scheduler functionality test',
            period:      60,
            isEnabled:   false,
            isSane:      true,
            isDebug:     false,
        );
    }
}
