<?php

use Phinx\Migration\AbstractMigration;

class UpgradeScheduler extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task', ['id' => false, 'primary_key' => 'periodic_task_id'])
             ->addColumn('periodic_task_id', 'integer', ['limit' => 10, 'signed' => false, 'identity' => true])
             ->addColumn('name', 'string', ['limit' => 64])
             ->addColumn('classname', 'string', ['limit' => 32])
             ->addColumn('description', 'string')
             ->addColumn('period', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('is_enabled', 'boolean', ['default' => true])
             ->addColumn('is_sane', 'boolean', ['default' => true])
             ->addColumn('is_debug', 'boolean', ['default' => false])
             ->addIndex(['name'], ['unique' => true])
             ->addIndex(['classname'], ['unique' => true])
             ->create();

        $this->table('periodic_task_history', ['id' => false, 'primary_key' => 'periodic_task_history_id'])
             ->addColumn('periodic_task_history_id', 'integer', ['limit' => 20, 'signed' => false, 'identity' => true])
             ->addColumn('periodic_task_id', 'integer', ['limit' => 10, 'signed' => false])
             ->addColumn('launch_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('status', 'enum', ['default' => 'running', 'values' => ['running', 'completed']])
             ->addColumn('num_errors', 'integer', ['default' => 0, 'limit' => 10, 'signed' => false])
             ->addColumn('num_items', 'integer', ['default' => 0, 'limit' => 10, 'signed' => false])
             ->addColumn('duration_ms', 'integer', ['default' => 0, 'limit' => 20, 'signed' => false])
             ->addForeignKey('periodic_task_id', 'periodic_task', 'periodic_task_id',
                 ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();

        $this->table('periodic_task_history_event', ['id' => false, 'primary_key' => 'periodic_task_history_event_id'])
             ->addColumn('periodic_task_history_event_id', 'integer', ['limit' => 20, 'signed' => false, 'identity' => true])
             ->addColumn('periodic_task_history_id', 'integer', ['limit' => 20, 'signed' => false])
             ->addColumn('severity', 'enum', ['values' => ['debug', 'info', 'error']])
             ->addColumn('event_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
             ->addColumn('event', 'string')
             ->addColumn('reference', 'integer', ['limit' => 10, 'signed' => false])
             ->addForeignKey('periodic_task_history_id', 'periodic_task_history', 'periodic_task_history_id',
                 ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();

        $fixtures = [
            [
                'name' => 'Test',
                'classname' => 'Test',
                'description' => 'Scheduler functionality test',
                'period' => 60,
                'is_enabled' => false
            ],
            // every
            [
                'name' => 'Calculate Contest Leaderboard',
                'classname' => 'CalculateContestLeaderboard',
                'description' => 'Calculates the leaderboard for the current contest and sends rewards',
                'period' => 60 * 15
            ],
            [
                'name' => 'Delete Tags',
                'classname' => 'DeleteTags',
                'description' => 'Deletes unpopular tags',
                'period' => 60 * 15
            ],
            [
                'name' => 'Expire FL Tokens',
                'classname' => 'ExpireFlTokens',
                'description' => 'Removes expired fl tokens',
                'period' => 60 * 15
            ],
            [
                'name' => 'Recovery',
                'classname' => 'Recovery',
                'description' => 'Validates requests and boosts upload',
                'period' => 60 * 15
            ],
            [
                'name' => 'Disable Stuck Tasks',
                'classname' => 'DisableStuckTasks',
                'description' => 'Disables long running tasks so they can be fixed',
                'period' => 60 * 15
            ],
            // hourly
            [
                'name' => 'Community Stats',
                'classname' => 'CommunityStats',
                'description' => 'Updates community stats section of user profiles',
                'period' => 60 * 60
            ],
            [
                'name' => 'Ratio Watch - Disable Leeching',
                'classname' => 'DisableLeechingRatioWatch',
                'description' => 'Disables leeching for users on ratio watch',
                'period' => 60 * 60
            ],
            [
                'name' => 'Expire Invites',
                'classname' => 'ExpireInvites',
                'description' => 'Expires old invites',
                'period' => 60 * 60
            ],
            [
                'name' => 'Front Page Stats',
                'classname' => 'FrontPageStats',
                'description' => 'Updates the stats for the front page',
                'period' => 60 * 60
            ],
            [
                'name' => 'Hide Old Requests',
                'classname' => 'HideOldRequests',
                'description' => 'Hides old filled requests',
                'period' => 60 * 60
            ],
            [
                'name' => 'Lower Login Attempts',
                'classname' => 'LowerLoginAttempts',
                'description' => 'Lowers login attempts and purges old attempts',
                'period' => 60 * 60
            ],
            [
                'name' => 'Remove Dead Peers',
                'classname' => 'RemoveDeadPeers',
                'description' => 'Removes dead peers',
                'period' => 60 * 60
            ],
            [
                'name' => 'Remove Dead Sessions',
                'classname' => 'RemoveDeadSessions',
                'description' => 'Removes sessions with no activity',
                'period' => 60 * 60
            ],
            [
                'name' => 'Remove Expired Warnings',
                'classname' => 'RemoveExpiredWarnings',
                'description' => 'Removes expired warnings',
                'period' => 60 * 60
            ],
            [
                'name' => 'Update Seed Times',
                'classname' => 'UpdateSeedTimes',
                'description' => "Updates seed times so Ocelot doesn't have to",
                'period' => 60 * 60
            ],
            [
                'name' => 'Bonus Points',
                'classname' => 'UpdateUserBonusPoints',
                'description' => 'Hands out bonus points',
                'period' => 60 * 60
            ],
            [
                'name' => 'User Stats - Daily',
                'classname' => 'UserStatsDaily',
                'description' => 'Updates daily user stat graphs',
                'period' => 60 * 60
            ],
            // daily
            [
                'name' => 'Torrent Reaper - Unseeded',
                'classname' => 'DeleteUnseededTorrents',
                'description' => 'Deletes unseeded torrents',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'Torrent Reaper - Never Seeded',
                'classname' => 'DeleteNeverSeededTorrents',
                'description' => 'Deletes torrents that were never seeded',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'Ratio Watch - Disable Download',
                'classname' => 'DisableDownloadingRatioWatch',
                'description' => 'Disables leech for users with poor ratio',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'User Reaper',
                'classname' => 'DisableInactiveUsers',
                'description' => 'Disables inactive users and warns the soon to be disabled',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'Unconfirmed User Disabler',
                'classname' => 'DisableUnconfirmedUsers',
                'description' => 'Disables users that never clicked the confirm link',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'Lock Old Threads',
                'classname' => 'LockOldThreads',
                'description' => 'Locks threads with no activity',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'Ratio Watch',
                'classname' => 'RatioWatch',
                'description' => 'Enables or disables ratio watch for users',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'Top 10 - Daily',
                'classname' => 'UpdateDailyTop10',
                'description' => 'Updates daily top 10 torrents',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'User Stats - Monthly',
                'classname' => 'UserStatsMonthly',
                'description' => 'Updates monthly user stat graphs',
                'period' => 60 * 60 * 24
            ],
            [
                'name' => 'Task History Purge',
                'classname' => 'PurgeOldTaskHistory',
                'description' => 'Purges old task history logs',
                'period' => 60 * 60 * 24
            ],
            // weekly
            [
                'name' => 'Donations',
                'classname' => 'Donations',
                'description' => 'Updates donor ranks',
                'period' => 60 * 60 * 24 * 7
            ],
            [
                'name' => 'Resolve Staff PMs',
                'classname' => 'ResolveStaffPms',
                'description' => 'Resolves old staff PMs',
                'period' => 60 * 60 * 24 * 7
            ],
            [
                'name' => 'Top 10 - Weekly',
                'classname' => 'UpdateWeeklyTop10',
                'description' => 'Updates weekly top 10 torrents',
                'period' => 60 * 60 * 24 * 7
            ],
            [
                'name' => 'User Stats - Yearly',
                'classname' => 'UserStatsYearly',
                'description' => 'Updates yearly user stat graphs',
                'period' => 60 * 60 * 24 * 7
            ],
            [
                'name' => 'Unseeded Notifications',
                'classname' => 'NotifyNonseedingUploaders',
                'description' => 'Sends warnings for unseeded torrents',
                'period' => 60 * 60 * 24 * 7
            ],
            // biweekly
            [
                'name' => 'Cycle Auth Keys',
                'classname' => 'CycleAuthKeys',
                'description' => 'Cyles user auth keys',
                'period' => 60 * 60 * 24 * 14
            ],
            // disabled
            [
                'name' => 'Freeleech',
                'classname' => 'Freeleech',
                'description' => 'Expires 6 (7) hour freeleeches',
                'period' => 60 * 15,
                'is_enabled' => false
            ],
            [
                'name' => 'Perfect FLAC Token Handout',
                'classname' => 'RewardPerfectFlacUploads',
                'description' => 'Hands out tokens and invites for perfect FLACs',
                'period' => 60 * 60,
                'is_enabled' => false
            ],
            [
                'name' => 'Update Geoip',
                'classname' => 'UpdateGeoip',
                'description' => 'Updates geoip distributions',
                'period' => 60 * 60 * 24,
                'is_enabled' => false
            ],
        ];

        $this->table('periodic_task')
             ->insert($fixtures)
             ->save();
    }

    public function down(): void {
        $this->table('periodic_task_history_event')->drop()->save();
        $this->table('periodic_task_history')->drop()->save();
        $this->table('periodic_task')->drop()->save();
    }
}
