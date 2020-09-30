<?php
// Begin user stats

$userMan = new Gazelle\Manager\User;
$UserStats = $userMan->globalActivityStats();
$UserCount = $userMan->getEnabledUsersCount();

// Begin request stats
if (($RequestStats = $Cache->get_value('stats_requests')) === false) {
    $RequestCount = $DB->scalar("
        SELECT count(*) FROM requests
    ");
    $FilledCount = $DB->scalar("
        SELECT count(*) FROM requests WHERE FillerID > 0
    ");
    $Cache->cache_value('stats_requests', [$RequestCount, $FilledCount], 11280);
} else {
    list($RequestCount, $FilledCount) = $RequestStats;
}

// Begin swarm stats
if (($PeerStats = $Cache->get_value('stats_peers')) === false) {
    //Cache lock!
    if ($Cache->get_query_lock('peer_stats')) {
        $DB->prepared_query("
            SELECT IF(remaining=0,'Seeding','Leeching') AS Type, COUNT(uid)
            FROM xbt_files_users
            WHERE active = 1
            GROUP BY Type
        ");
        $PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
        $LeecherCount = isset($PeerCount['Leeching']) ? $PeerCount['Leeching'][1] : 0;
        $SeederCount = isset($PeerCount['Seeding']) ? $PeerCount['Seeding'][1] : 0;
        $Cache->cache_value('stats_peers', [$LeecherCount, $SeederCount], 1209600); // 2 week cache
        $Cache->clear_query_lock('peer_stats');
    } else {
        $LeecherCount = $SeederCount = 0;
    }
} else {
    list($LeecherCount, $SeederCount) = $PeerStats;
}

$torrentStatsMan = new Gazelle\Stats\Torrent;
json_print("success", [
    'maxUsers' => USER_LIMIT,
    'enabledUsers' => (int) $UserCount,
    'usersActiveThisDay' => (int) $UserStats['Day'],
    'usersActiveThisWeek' => (int) $UserStats['Week'],
    'usersActiveThisMonth' => (int) $UserStats['Month'],

    'torrentCount'     => $torrentStatsMan->torrentCount(),
    'releaseCount'     => $torrentStatsMan->albumCount(),
    'artistCount'      => $torrentStatsMan->artistCount(),
    'perfectFlacCount' => $torrentStatsMan->perfectCount(),

    'requestCount' => (int) $RequestCount,
    'filledRequestCount' => (int) $FilledCount,

    'seederCount' => (int) $SeederCount,
    'leecherCount' => (int) $LeecherCount
]);
