<?php
// We keep torrent groups cached. However, the peer counts change often, so the solution is to not cache them for long, or to update them. Here is where we update them.

if ((!isset($argv[1]) || $argv[1]!=SCHEDULE_KEY) && !check_perms('admin_schedule')) { // authorization, Fix to allow people with perms hit this page.
    error(403);
}

if (check_perms('admin_schedule')) {
    View::show_header();
    echo '<pre>';
}

ignore_user_abort();
ini_set('max_execution_time', 300);
ob_end_flush();
gc_enable();

$Cache->InternalCache = false; // We don't want PHP to cache all results internally
$DB->prepared_query('
    CREATE TEMPORARY TABLE tmp_torrents_peerlists (
        TorrentID int NOT NULL PRIMARY KEY,
        GroupID   int,
        Seeders   int,
        Leechers  int,
        Snatches  int
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8
');
$DB->prepared_query('
    INSERT INTO tmp_torrents_peerlists
    SELECT t.ID, t.GroupID, tls.Seeders, tls.Leechers, tls.Snatched
    FROM torrents t
    INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
');

$DB->prepared_query('
    CREATE TEMPORARY TABLE tpc_temp (
        TorrentID int,
        GroupID   int,
        Seeders   int,
        Leechers  int,
        Snatched  int,
        PRIMARY KEY (GroupID, TorrentID)
    )
');
$DB->prepared_query('
    INSERT INTO tpc_temp
    SELECT t2.*
    FROM torrents_peerlists AS t1
    INNER JOIN tmp_torrents_peerlists AS t2 USING (TorrentID)
    WHERE t1.Seeders != t2.Seeders
        OR t1.Leechers != t2.Leechers
        OR t1.Snatches != t2.Snatches
');

$StepSize = 30000;
$DB->prepared_query('
    SELECT TorrentID, GroupID, Seeders, Leechers, Snatched
    FROM tpc_temp
    ORDER BY GroupID ASC, TorrentID ASC
    LIMIT ?
    ', $StepSize
);

$RowNum = 0;
$LastGroupID = 0;
$UpdatedKeys = $UncachedGroups = 0;
list($TorrentID, $GroupID, $Seeders, $Leechers, $Snatches) = $DB->next_record(MYSQLI_NUM, false);
while ($TorrentID) {
    if ($LastGroupID != $GroupID) {
        $CachedData = $Cache->get_value("torrent_group_$GroupID");
        if ($CachedData !== false) {
            if (isset($CachedData['ver']) && $CachedData['ver'] == Gazelle\Cache::GROUP_VERSION) {
                $CachedStats = &$CachedData['d']['Torrents'];
            }
        } else {
            $UncachedGroups++;
        }
        $LastGroupID = $GroupID;
    }
    while ($LastGroupID == $GroupID) {
        $RowNum++;
        if (isset($CachedStats) && is_array($CachedStats[$TorrentID])) {
            $OldValues = &$CachedStats[$TorrentID];
            $OldValues['Seeders'] = $Seeders;
            $OldValues['Leechers'] = $Leechers;
            $OldValues['Snatched'] = $Snatches;
            $Changed = true;
            unset($OldValues);
        }
        if (!($RowNum % $StepSize)) {
            $DB->prepared_query('
                SELECT TorrentID, GroupID, Seeders, Leechers, Snatched
                FROM tpc_temp
                WHERE (GroupID > ? OR (GroupID = ? AND TorrentID > ?))
                ORDER BY GroupID ASC, TorrentID ASC
                LIMIT ?
                ', $GroupID, $GroupID, $TorrentID, $StepSize
            );
        }
        $LastGroupID = $GroupID;
        list($TorrentID, $GroupID, $Seeders, $Leechers, $Snatches) = $DB->next_record(MYSQLI_NUM, false);
    }
    if ($Changed) {
        $Cache->cache_value("torrent_group_$LastGroupID", $CachedData, 0);
        unset($CachedStats);
        $UpdatedKeys++;
        $Changed = false;
    }
}
global $Debug;
printf("Updated %d keys, skipped %d keys in %.6fs (%d kB memory)\n", $UpdatedKeys, $UncachedGroups, microtime(true) - $Debug->startTime(), memory_get_usage(true) >> 10);

$DB->prepared_query("DELETE FROM torrents_peerlists");
$DB->prepared_query("
    INSERT INTO torrents_peerlists
    SELECT *
    FROM tmp_torrents_peerlists
");

if (check_perms('admin_schedule')) {
    echo '<pre>';
    View::show_footer();
}
