<?php

//------------- Ratio requirements

// Clear old seed time history
$DB->query('
    DELETE FROM users_torrent_history
    WHERE Date < DATE(now() - INTERVAL 7 DAY) + 0
');

// Store total seeded time for each user in a temp table
$DB->query('
    CREATE TEMPORARY TABLE tmp_history_time (
        UserID int(10) unsigned NOT NULL PRIMARY KEY,
        NumTorrents int(6) unsigned NOT NULL DEFAULT 0,
        SumTime bigint(20) unsigned NOT NULL DEFAULT 0,
        SeedingAvg int(6) unsigned NOT NULL DEFAULT 0,
        KEY numtorrents_idx (NumTorrents)
    ) ENGINE=InnoDB
');
$DB->query('
    INSERT INTO tmp_history_time
        (UserID, SumTime)
    SELECT UserID, SUM(Time)
    FROM users_torrent_history
    GROUP BY UserID
');

// Insert new row with <NumTorrents> = 0 with <Time> being number of seconds short of 72 hours.
// This is where we penalize torrents seeded for less than 72 hours
$DB->query('
    INSERT INTO users_torrent_history
        (UserID, NumTorrents, Date, Time)
    SELECT UserID, 0, UTC_DATE() + 0, 259200 - SumTime
    FROM tmp_history_time
    WHERE SumTime < 259200
');

// Set <Weight> to the time seeding <NumTorrents> torrents
$DB->query('
    UPDATE users_torrent_history
    SET Weight = NumTorrents * Time
');

// Calculate average time spent seeding each of the currently active torrents.
// This rounds the results to the nearest integer because SeedingAvg is an int column.
$DB->query('
    CREATE TEMPORARY TABLE tmp_history_weight_time (
        UserID int(10) unsigned NOT NULL PRIMARY KEY,
        NumTorrents int(6) unsigned NOT NULL DEFAULT 0,
        SumTime bigint(20) unsigned NOT NULL DEFAULT 0,
        SeedingAvg int(6) unsigned NOT NULL DEFAULT 0,
        KEY numtorrents_idx (NumTorrents)
    ) ENGINE=InnoDB
');
$DB->query('
    INSERT INTO tmp_history_weight_time
        (UserID, SeedingAvg)
    SELECT UserID, SUM(Weight) / SUM(Time)
    FROM users_torrent_history
    GROUP BY UserID
');

// Remove dummy entry for torrents seeded less than 72 hours
$DB->query("
    DELETE FROM users_torrent_history
    WHERE NumTorrents = '0'
");

// Get each user's amount of snatches of existing torrents
$DB->query('
    CREATE TEMPORARY TABLE tmp_snatch (
        UserID int unsigned PRIMARY KEY,
        NumSnatches int(10) unsigned NOT NULL DEFAULT 0
    ) ENGINE=InnoDB
');

$DB->query('
    INSERT INTO tmp_snatch (UserID, NumSnatches)
    SELECT xs.uid, COUNT(DISTINCT xs.fid)
    FROM xbt_snatched AS xs
    INNER JOIN torrents AS t ON (t.ID = xs.fid)
    GROUP BY xs.uid
');

// Get the fraction of snatched torrents seeded for at least 72 hours this week
// Essentially take the total number of hours seeded this week and divide that by 72 hours * <NumSnatches>
$DB->query('
    UPDATE users_main AS um
    INNER JOIN tmp_history_weight_time AS t ON (t.UserID = um.ID)
    INNER JOIN tmp_snatch AS s ON (s.UserID = um.ID)
    SET um.RequiredRatioWork = (1 - (t.SeedingAvg / s.NumSnatches))
    WHERE s.NumSnatches > 0
');

$RatioRequirements = [
    [80 * 1024 * 1024 * 1024, 0.60, 0.50],
    [60 * 1024 * 1024 * 1024, 0.60, 0.40],
    [50 * 1024 * 1024 * 1024, 0.60, 0.30],
    [40 * 1024 * 1024 * 1024, 0.50, 0.20],
    [30 * 1024 * 1024 * 1024, 0.40, 0.10],
    [20 * 1024 * 1024 * 1024, 0.30, 0.05],
    [10 * 1024 * 1024 * 1024, 0.20, 0.0],
    [ 5 * 1024 * 1024 * 1024, 0.15, 0.0]
];

$DownloadBarrier = 100 * 1024 * 1024 * 1024;
$DB->prepared_query('
    UPDATE users_main AS um
    INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
    SET um.RequiredRatio = 0.60
    WHERE uls.Downloaded > ?
    ', $DownloadBarrier
);

foreach ($RatioRequirements as $Requirement) {
    list($Download, $Ratio, $MinRatio) = $Requirement;

    $DB->prepared_query('
        UPDATE users_main AS um
        INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
        SET um.RequiredRatio = um.RequiredRatioWork * ?
        WHERE uls.Downloaded >= ?
        AND uls.Downloaded < ?
        ', $Ratio, $Download, $DownloadBarrier
    );

    $DB->prepared_query('
        UPDATE users_main AS um
        INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
        SET um.RequiredRatio = ?
        WHERE uls.Downloaded >= ?
            AND uls.Downloaded < ?
            AND um.RequiredRatio < ?
        ', $MinRatio, $Download, $DownloadBarrier, $MinRatio
    );

    $DownloadBarrier = $Download;
}

$DB->query('
    UPDATE users_main AS um
    INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
    SET um.RequiredRatio = 0.00
    WHERE uls.Downloaded < 5 * 1024 * 1024 * 1024
');
