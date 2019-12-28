<?php

//------------- Record who's seeding how much, used for ratio watch

$DB->query('
    CREATE TEMPORARY TABLE tmp_users_torrent_history (
        UserID int(10) unsigned NOT NULL PRIMARY KEY,
        NumTorrents int(6) unsigned NOT NULL DEFAULT 0,
        SumTime bigint(20) unsigned NOT NULL DEFAULT 0,
        SeedingAvg int(6) unsigned NOT NULL DEFAULT 0,
        KEY numtorrents_idx (NumTorrents)
    ) ENGINE=InnoDB
');

// Find seeders that have announced within the last hour
$DB->query('
    INSERT INTO tmp_users_torrent_history
        (UserID, NumTorrents)
    SELECT uid, COUNT(DISTINCT fid)
    FROM xbt_files_users
    WHERE mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
        AND Remaining = 0
    GROUP BY uid
');

// Mark new records as "checked" and set the current time as the time
// the user started seeding <NumTorrents> seeded.
// Finished = 1 means that the user hasn't been seeding exactly <NumTorrents> earlier today.
// This query will only do something if the next one inserted new rows last hour.
$DB->query("
    UPDATE users_torrent_history AS h
    INNER JOIN tmp_users_torrent_history AS t ON (t.UserID = h.UserID AND t.NumTorrents = h.NumTorrents)
    SET h.Finished = '0',
        h.LastTime = UNIX_TIMESTAMP(now())
    WHERE h.Finished = '1'
        AND h.Date = UTC_DATE() + 0
");

// Insert new rows for users who haven't been seeding exactly <NumTorrents> torrents earlier today
// and update the time spent seeding <NumTorrents> torrents for the others.
// Primary table index: (UserID, NumTorrents, Date).
$DB->query('
    INSERT INTO users_torrent_history
        (UserID, NumTorrents, Date)
    SELECT UserID, NumTorrents, UTC_DATE() + 0
    FROM tmp_users_torrent_history
    ON DUPLICATE KEY UPDATE
        Time = Time + UNIX_TIMESTAMP(now()) - LastTime,
        LastTime = UNIX_TIMESTAMP(now())
');
