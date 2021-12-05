<?php

namespace Gazelle\Schedule\Tasks;

class RatioRequirements extends \Gazelle\Schedule\Task
{
    public function run()
    {
        // Clear old seed time history
        self::$db->prepared_query('
            DELETE FROM users_torrent_history
            WHERE Date < DATE(now() - INTERVAL 7 DAY)
        ');

        // Store total seeded time for each user in a temp table
        self::$db->prepared_query('
            CREATE TEMPORARY TABLE tmp_history_time (
                UserID int(10) unsigned NOT NULL PRIMARY KEY,
                SumTime bigint(20) unsigned NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
            SELECT UserID, SUM(Time) as SumTime
            FROM users_torrent_history
            GROUP BY UserID
        ');

        // Insert new row with <NumTorrents> = 0 with <Time> being number of seconds short of 72 hours.
        // This is where we penalize torrents seeded for less than 72 hours
        self::$db->prepared_query('
            INSERT INTO users_torrent_history
                (UserID, NumTorrents, Date, Time)
            SELECT UserID, 0, UTC_DATE() + 0, 259200 - SumTime
            FROM tmp_history_time
            WHERE SumTime < 259200
        ');

        // Set <Weight> to the time seeding <NumTorrents> torrents
        self::$db->prepared_query('
            UPDATE users_torrent_history
            SET Weight = NumTorrents * Time
            WHERE Weight != NumTorrents * Time
        ');

        // Calculate average time spent seeding each of the currently active torrents.
        // This rounds the results to the nearest integer because SeedingAvg is an int column.
        self::$db->prepared_query('
            CREATE TEMPORARY TABLE tmp_history_weight_time (
                UserID int(10) unsigned NOT NULL PRIMARY KEY,
                SeedingAvg int(6) unsigned NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
            SELECT UserID, SUM(Weight) / SUM(Time) as SeedingAvg
            FROM users_torrent_history
            GROUP BY UserID
        ');

        // Remove dummy entry for torrents seeded less than 72 hours
        self::$db->prepared_query('
            DELETE FROM users_torrent_history
            WHERE NumTorrents = 0
        ');

        // Get each user's amount of snatches of existing torrents
        self::$db->prepared_query('
            CREATE TEMPORARY TABLE tmp_snatch (
                UserID int unsigned PRIMARY KEY,
                NumSnatches int(10) unsigned NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
            SELECT xs.uid as UserID, COUNT(DISTINCT xs.fid) as NumSnatches
            FROM xbt_snatched AS xs
            INNER JOIN torrents AS t ON (t.ID = xs.fid)
            GROUP BY xs.uid
        ');

        // Get the fraction of snatched torrents seeded for at least 72 hours this week
        // Essentially take the total number of hours seeded this week and divide that by 72 hours * <NumSnatches>
        self::$db->prepared_query('
            CREATE TEMPORARY TABLE tmp_snatch_weight (
                UserID int unsigned PRIMARY KEY,
                fraction float(10) NOT NULL
            ) ENGINE=InnoDB
            SELECT t.UserID, 1 - (t.SeedingAvg / s.NumSnatches) as fraction
            FROM tmp_history_weight_time AS t
            INNER JOIN tmp_snatch AS s USING (UserID)
        ');

        $ratioRequirements = [
            [80 * 1024 * 1024 * 1024, 0.60, 0.50],
            [60 * 1024 * 1024 * 1024, 0.60, 0.40],
            [50 * 1024 * 1024 * 1024, 0.60, 0.30],
            [40 * 1024 * 1024 * 1024, 0.50, 0.20],
            [30 * 1024 * 1024 * 1024, 0.40, 0.10],
            [20 * 1024 * 1024 * 1024, 0.30, 0.05],
            [10 * 1024 * 1024 * 1024, 0.20, 0.0],
            [ 5 * 1024 * 1024 * 1024, 0.15, 0.0]
        ];

        $downloadBarrier = 100 * 1024 * 1024 * 1024;
        self::$db->prepared_query('
            UPDATE users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            SET um.RequiredRatio = 0.60
            WHERE uls.Downloaded > ?
            ', $downloadBarrier
        );

        foreach ($ratioRequirements as $requirement) {
            list($download, $ratio, $minRatio) = $requirement;

            self::$db->prepared_query('
                UPDATE users_main AS um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                INNER JOIN tmp_snatch_weight AS tsw ON (uls.UserID = um.ID)
                SET um.RequiredRatio = tsw.fraction * ?
                WHERE uls.Downloaded >= ?
                    AND uls.Downloaded < ?
                ', $ratio, $download, $downloadBarrier
            );

            self::$db->prepared_query('
                UPDATE users_main AS um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                SET um.RequiredRatio = ?
                WHERE uls.Downloaded >= ?
                    AND uls.Downloaded < ?
                    AND um.RequiredRatio < ?
                ', $minRatio, $download, $downloadBarrier, $minRatio
            );

            $downloadBarrier = $download;
        }

        self::$db->prepared_query('
            UPDATE users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            SET um.RequiredRatio = 0.00
            WHERE uls.Downloaded < 5 * 1024 * 1024 * 1024
        ');
    }
}
