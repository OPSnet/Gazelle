<?php
// Begin user stats
$UserCount = Users::get_enabled_users_count();

$UserStats = \Gazelle\User::globalActivityStats($DB, $Cache);

// Begin torrent stats
if (($TorrentCount = $Cache->get_value('stats_torrent_count')) === false) {
    $TorrentCount = $DB->scalar("
        SELECT count(*) FROM torrents
    ");
    $Cache->cache_value('stats_torrent_count', $TorrentCount, 604800); // staggered 1 week cache
}

if (($AlbumCount = $Cache->get_value('stats_album_count')) === false) {
    $AlbumCount = $DB->scalar("
        SELECT count(*) FROM torrents_group WHERE CategoryID = 1
    ");
    $Cache->cache_value('stats_album_count', $AlbumCount, 604830); // staggered 1 week cache
}

if (($ArtistCount = $Cache->get_value('stats_artist_count')) === false) {
    $ArtistCount = $DB->scalar("
        SELECT count(*) FROM artists_group
    ");
    $Cache->cache_value('stats_artist_count', $ArtistCount, 604860); // staggered 1 week cache
}

if (($PerfectCount = $Cache->get_value('stats_perfect_count')) === false) {
    $PerfectCount = $DB->scalar("
        SELECT count(*)
        FROM torrents
        WHERE Format = 'FLAC'
            AND (
                (Media = 'CD' AND LogChecksum = '1' AND HasCue = '1' AND HasLogDB = '1' AND LogScore = 100)
                OR
                (Media in ('BD', 'DVD', 'Soundboard', 'WEB', 'Vinyl'))
            )
    ");
    $Cache->cache_value('stats_perfect_count', $PerfectCount, 3600); // staggered 1 week cache
}

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

json_print("success", [
    'maxUsers' => USER_LIMIT,
    'enabledUsers' => (int) $UserCount,
    'usersActiveThisDay' => (int) $UserStats['Day'],
    'usersActiveThisWeek' => (int) $UserStats['Week'],
    'usersActiveThisMonth' => (int) $UserStats['Month'],

    'torrentCount' => (int) $TorrentCount,
    'releaseCount' => (int) $AlbumCount,
    'artistCount' => (int) $ArtistCount,
    'perfectFlacCount' => (int) $PerfectCount,

    'requestCount' => (int) $RequestCount,
    'filledRequestCount' => (int) $FilledCount,

    'seederCount' => (int) $SeederCount,
    'leecherCount' => (int) $LeecherCount
]);
