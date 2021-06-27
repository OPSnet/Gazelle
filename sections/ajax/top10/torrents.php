<?php
$torrent = new \Gazelle\Top10\Torrent(FORMAT, $LoggedUser);
$details = isset($_GET['details']) && in_array($_GET['details'], ['day', 'week', 'overall', 'snatched', 'data', 'seeded', 'month', 'year']) ? $_GET['details'] : 'all';

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

$OuterResults = [];

if ($details == 'all' || $details == 'day') {
    $topTorrentsActiveLastDay = $torrent->getTopTorrents($_GET, 'day', $limit);
    $OuterResults[] = generate_torrent_json('Most Active Torrents Uploaded in the Past Day', 'day', $topTorrentsActiveLastDay, $limit);
}

if ($details == 'all' || $details == 'week') {
    $topTorrentsActiveLastWeek = $torrent->getTopTorrents($_GET, 'week', $limit);
    $OuterResults[] = generate_torrent_json('Most Active Torrents Uploaded in the Past Week', 'week', $topTorrentsActiveLastWeek, $limit);
}

if ($details == 'all' || $details == 'month') {
    $topTorrentsActiveLastMonth = $torrent->getTopTorrents($_GET, 'month', $limit);
    $OuterResults[] = generate_torrent_json('Most Active Torrents Uploaded in the Past Week', 'week', $topTorrentsActiveLastMonth, $limit);
}

if ($details == 'all' || $details == 'year') {
    $topTorrentsActiveLastYear = $torrent->getTopTorrents($_GET, 'year', $limit);
    $OuterResults[] = generate_torrent_json('Most Active Torrents Uploaded in the Past Week', 'week', $topTorrentsActiveLastYear, $limit);
}

if ($details == 'all' || $details == 'overall') {
    $topTorrentsActiveAllTime = $torrent->getTopTorrents($_GET, 'overall', $limit);
    $OuterResults[] = generate_torrent_json('Most Active Torrents of All Time', 'overall', $topTorrentsActiveAllTime, $limit);
}

if (($details == 'all' || $details == 'snatched')) {
    $topTorrentsSnatched = $torrent->getTopTorrents($_GET, 'snatched', $limit);
    $OuterResults[] = generate_torrent_json('Most Snatched Torrents', 'snatched', $topTorrentsSnatched, $limit);
}

if (($details == 'all' || $details == 'data')) {
    $topTorrentsTransferred = $torrent->getTopTorrents($_GET, 'data', $limit);
    $OuterResults[] = generate_torrent_json('Most Data Transferred Torrents', 'data', $topTorrentsTransferred, $limit);
}

if (($details == 'all' || $details == 'seeded')) {
    $topTorrentsSeeded = $torrent->getTopTorrents($_GET, 'seeded', $limit);
    $OuterResults[] = generate_torrent_json('Best Seeded Torrents', 'seeded', $topTorrentsSeeded, $limit);
}

print
    json_encode(
        [
            'status' => 'success',
            'response' => $OuterResults
        ]
    );


function generate_torrent_json($caption, $tag, $details, $limit) {
    global $LoggedUser;
    $results = [];
    foreach ($details as $detail) {
        list($torrentID, $groupID, $groupName, $groupCategoryID, $wikiImage, $tagsList,
            $format, $encoding, $media, $scene, $hasLog, $hasCue, $hasLogDB, $logScore, $logChecksum, $year, $groupYear,
            $remasterTitle, $snatched, $seeders, $leechers, $data, $releaseType, $size) = $detail;

        $artist = Artists::display_artists(Artists::get_artist($groupID), false, true);
        $truncatedArtist = substr($artist, 0, strlen($artist) - 3);

        // Append to the existing array.
        $results[] = [
            'torrentId' => (int)$torrentID,
            'groupID' => (int)$groupID,
            'artist' => $truncatedArtist,
            'groupName' => $groupName,
            'groupCategory' => (int)$groupCategoryID,
            'groupYear' => (int)$groupYear,
            'remasterTitle' => $remasterTitle,
            'format' => $format,
            'encoding' => $encoding,
            'hasLog' => $hasLog == 1,
            'hasCue' => $hasCue == 1,
            'hasLogDB' => $hasLogDB == 1,
            'logScore' => $logScore,
            'logChecksum' => $logChecksum,
            'media' => $media,
            'scene' => $scene == 1,
            'year' => (int)$year,
            'tags' => explode(' ', $tagsList),
            'snatched' => (int)$snatched,
            'seeders' => (int)$seeders,
            'leechers' => (int)$leechers,
            'data' => (int)$data,
            'size' => (int)$size,
            'wikiImage' => $wikiImage,
            'releaseType' => $releaseType,
        ];
    }

    return [
        'caption' => $caption,
        'tag' => $tag,
        'limit' => (int)$limit,
        'results' => $results
    ];
}
