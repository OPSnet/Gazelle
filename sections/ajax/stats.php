<?php
// Begin user stats

$userMan   = new Gazelle\Manager\User;
$userStats = new Gazelle\Stats\Users;
$torrStats = new Gazelle\Stats\Torrent;

$UserStats = $userStats->globalActivityStats();
$UserCount = $userMan->getEnabledUsersCount();

// Begin request stats
if (($RequestStats = $Cache->get_value('stats_requests')) === false) {
    $RequestCount = (int)$DB->scalar("
        SELECT count(*) FROM requests
    ");
    $FilledCount = (int)$DB->scalar("
        SELECT count(*) FROM requests WHERE FillerID > 0
    ");
    $Cache->cache_value('stats_requests', [$RequestCount, $FilledCount], 11280);
} else {
    [$RequestCount, $FilledCount] = $RequestStats;
}

$PeerStats = $Cache->get_value('stats_peers');
if ($PeerStats === false) {
    $userStats->frontPage();
    $PeerStats = $Cache->get_value('stats_peers');
}

json_print("success", [
    'maxUsers'             => USER_LIMIT,
    'enabledUsers'         => $UserCount,
    'usersActiveThisDay'   => $UserStats['Day'],
    'usersActiveThisWeek'  => $UserStats['Week'],
    'usersActiveThisMonth' => $UserStats['Month'],

    'torrentCount'     => $torrStats->torrentCount(),
    'releaseCount'     => $torrStats->albumCount(),
    'artistCount'      => $torrStats->artistCount(),
    'perfectFlacCount' => $torrStats->perfectCount(),

    'requestCount'       => $RequestCount,
    'filledRequestCount' => $FilledCount,

    'seederCount'  => $PeerStats[0],
    'leecherCount' => $PeerStats[1],
]);
