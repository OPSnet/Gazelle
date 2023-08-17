<?php

use PHPUnit\Framework\TestCase;
// use PHPUnit\Framework\Attributes\DataProvider;

require_once(__DIR__ . '/../../lib/bootstrap.php');
ini_set('memory_limit', '1G');

class SchedulerTest extends TestCase {
    public function testRun(): void {
        $scheduler = new Gazelle\TaskScheduler;
        $this->expectOutputRegex('/^(?:\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[(?:debug|info)\] (.*?)\n|Running task (?:.*?)\.\.\.DONE! \(\d+\.\d+\)\n)*$/');
        $scheduler->run();
    }

    public function testRunWithMissingImplementation(): void {
        $scheduler = new Gazelle\TaskScheduler;
        $name = "RunUnimplemented";
        $db = Gazelle\DB::DB();
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
        $scheduler = new Gazelle\TaskScheduler;
        $this->assertEquals(-1, $scheduler->runClass("NoSuchClassname"), "sched-task-no-such-class");
    }

    public function testMissingImplementation(): void {
        $scheduler = new Gazelle\TaskScheduler;
        $name = "Unimplemented";
        $db = Gazelle\DB::DB();
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
        $this->assertEquals(-1, $scheduler->runClass($name), "sched-task-unimplemented");
        $db->prepared_query("
            DELETE FROM periodic_task WHERE classname = ?
            ", $name
        );
    }

    // for phpunit v10: #[DataProvider('taskProvider')]
    /**
     * @dataProvider taskProvider
     */
    public function testTask(string $taskName): void {
        $scheduler = new Gazelle\TaskScheduler;
        ob_start();
        $this->assertIsInt($scheduler->runClass($taskName), "sched-task-$taskName");
        ob_end_clean();
    }

    public static function taskProvider(): array {
        $taskList = [
            ['NoSuchTaskEither'],
            ['ArtistUsage'],
            ['BetterTranscode'],
            ['CalculateContestLeaderboard'],
            ['CommunityStats'],
            ['CycleAuthKeys'],
            ['DeleteTags'],
            ['DemoteUsers'],
            ['DemoteUsersRatio'],
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
            ['NotifyNonseedingUploaders'],
            ['Peerupdate'],
            ['PromoteUsers'],
            ['PurgeOldTaskHistory'],
            ['RatioRequirements'],
            ['RatioWatch'],
            ['RemoveDeadSessions'],
            ['RemoveExpiredWarnings'],
            ['ResolveStaffPms'],
            ['SSLCertificate'],
            ['Test'],
            ['TorrentHistory'],
            ['UpdateDailyTop10'],
            ['UpdateSeedTimes'],
            ['UpdateUserBonusPoints'],
            ['UpdateUserTorrentHistory'],
            ['UpdateWeeklyTop10'],
            ['UserLastAccess'],
            ['UserStatsDaily'],
            ['UserStatsMonthly'],
            ['UserStatsYearly'],
        ];

        if (getenv('CI') !== false) {
            // too dangerous to run locally
            $taskList[] = ['DeleteNeverSeededTorrents'];
            $taskList[] = ['DeleteUnseededTorrents'];
        }

        return $taskList;
    }
}
