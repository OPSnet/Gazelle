<?php

$details = isset($_GET['details']) && in_array($_GET['details'], ['day', 'week', 'overall', 'snatched', 'data', 'seeded', 'month', 'year']) ? $_GET['details'] : 'all';

$limit = (int)($_GET['limit'] ?? 10);
$limit = in_array($limit, [10, 100, 250]) && $details !== 'all' ? $limit : 10;

$top10  = new Gazelle\Top10\Torrent(FORMAT, $Viewer);
$torMan = new Gazelle\Manager\Torrent;
$result = [];

// Filter out common parameters that are not needed in getTopTorrents to get more cache hits
$getParameters = array_filter($_GET, fn($k) => !in_array($k, ['action', 'details', 'limit', 'type']), ARRAY_FILTER_USE_KEY);

if ($details == 'all' || $details == 'day') {
    $result[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Day',
        'tag'     => 'day',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'day', $limit)),
    ];
}

if ($details == 'all' || $details == 'week') {
    $result[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Week',
        'tag'     => 'week',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'week', $limit)),
    ];
}

if ($details == 'all' || $details == 'month') {
    $result[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Month',
        'tag'     => 'month',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'month', $limit)),
    ];
}

if ($details == 'all' || $details == 'year') {
    $result[] = [
        'caption' => 'Most Active Torrents Uploaded in the Past Year',
        'tag'     => 'year',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'year', $limit)),
    ];
}

if ($details == 'all' || $details == 'overall') {
    $result[] = [
        'caption' => 'Most Active Torrents Uploaded of All Time',
        'tag'     => 'overall',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'overall', $limit)),
    ];
}

if ($details == 'all' || $details == 'snatched') {
    $result[] = [
        'caption' => 'Most Snatched  Torrents',
        'tag'     => 'snatched',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'snatched', $limit)),
    ];
}

if ($details == 'all' || $details == 'data') {
    $result[] = [
        'caption' => 'Most Data Transferred Torrents',
        'tag'     => 'data',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'data', $limit)),
    ];
}

if ($details == 'all' || $details == 'seeded') {
    $result[] = [
        'caption' => 'Best Seeded Torrents',
        'tag'     => 'seeded',
        'limit'   => $limit,
        'results' => payload($torMan, $top10->getTopTorrents($getParameters, 'seeded', $limit)),
    ];
}

print json_encode([
    'status'   => 'success',
    'response' => $result
]);

function payload(Gazelle\Manager\Torrent $torMan, array $details): array {
    $results = [];
    foreach ($details as $detail) {
        $torrent = $torMan->findById($detail[0]);
        if (is_null($torrent)) {
            continue;
        }
        $tgroup = $torrent->group();
        $results[] = [
            'torrentId'     => $torrent->id(),
            'groupID'       => $torrent->groupId(),
            'artist'        => $tgroup->artistName(),
            'groupName'     => $tgroup->name(),
            'groupCategory' => $tgroup->categoryId(),
            'groupYear'     => $tgroup->year(),
            'remasterTitle' => $torrent->remasterTitle(),
            'format'        => $torrent->format(),
            'encoding'      => $torrent->encoding(),
            'hasLog'        => $torrent->hasLog(),
            'hasCue'        => $torrent->hasCue(),
            'hasLogDB'      => $torrent->hasLogDb(),
            'logScore'      => $torrent->logScore(),
            'logChecksum'   => $torrent->logChecksum(),
            'media'         => $torrent->media(),
            'scene'         => $torrent->isScene(),
            'year'          => $torrent->remasterYear(),
            'tags'          => array_values($tgroup->tagNameList()),
            'snatched'      => $torrent->snatchTotal(),
            'seeders'       => $torrent->seederTotal(),
            'leechers'      => $torrent->leecherTotal(),
            'data'          => $torrent->size() * $torrent->snatchTotal() + $torrent->size() * $torrent->leecherTotal() * 0.5,
            'size'          => $torrent->size(),
            'wikiImage'     => $tgroup->image(),
            'releaseType'   => $tgroup->releaseType(),
        ];
    }
    return $results;
}
