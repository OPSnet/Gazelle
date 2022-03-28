<?php

if (empty($_GET['order_by']) || !isset(Gazelle\Search\Torrent::$SortOrders[$_GET['order_by']])) {
    $OrderBy = 'time';
} else {
    $OrderBy = $_GET['order_by'];
}
$OrderWay = ($_GET['order_way'] ?? 'desc');
$GroupResults = ($_GET['group_results'] ?? '1') != '0';
$Page = (int)($_GET['page'] ?? 1);

$Search = new Gazelle\Search\Torrent(
    $GroupResults,
    $OrderBy,
    $OrderWay,
    $Page,
    TORRENTS_PER_PAGE,
    $Viewer->permitted('site_search_many')
);
$Results    = $Search->query($_GET);
$NumResults = $Search->record_count();
if (!$Viewer->permitted('site_search_many')) {
    $NumResults = min($NumResults, SPHINX_MAX_MATCHES);
}

if ($Results === false) {
    json_die('failure', 'Search returned an error. Make sure all parameters are valid and of the expected types.');
}
if ($NumResults == 0) {
    json_die('success', [
        'results' => [],
        'youMightLike' => [] // This slow and broken feature has been removed
    ]);
}

$bookmark   = new Gazelle\Bookmark($Viewer);
$releaseMan = new Gazelle\ReleaseType;
$tgMan      = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$torMan     = (new Gazelle\Manager\Torrent)->setViewer($Viewer);

$JsonGroups = [];
foreach ($Results as $Key => $GroupID) {
    $tgroup = $tgMan->findById($GroupID);
    if (is_null($tgroup)) {
        continue;
    }
    $Torrents = [];
    if ($GroupResults) {
        $torrentIdList = $tgroup->torrentIdList();
        $GroupTime = 0;
        $MaxSize   = 0;
        foreach ($torrentIdList as $torrentId) {
            $torrent = $torMan->findById($torrentId);
            if (is_null($torrent)) {
                continue;
            }
            $GroupTime  = max($GroupTime, strtotime($torrent->uploadDate()));
            $MaxSize    = max($MaxSize, $torrent->size());
            $Torrents[] = $torrent;
        }
    } else {
        $torrent = $torMan->findById($Key);
        if ($torrent) {
            $Torrents[] = $torrent;
        }
    }

    $TagList = array_values($tgroup->tagNameList());
    $JsonArtists = (new Gazelle\ArtistRole\TGroup($tgroup->id()))->rolelist()['main'];
    $snatcher    = new Gazelle\User\Snatch($Viewer);
    if ($GroupResults && (count($Torrents) > 1 || $tgroup->categoryGrouped())) {
        $prev = false;
        $EditionID = 0;
        unset($FirstUnknown);

        $JsonTorrents = [];
        foreach ($Torrents as $torrent) {
            $current = $torrent->remasterTuple();

            if ($torrent->isRemasteredUnknown()) {
                $FirstUnknown = !isset($FirstUnknown);
            }
            if ($tgroup->categoryGrouped() && ($prev != $current || (isset($FirstUnknown) && $FirstUnknown))) {
                $EditionID++;
            }
            $prev = $current;

            $JsonTorrents[] = [
                'torrentId'      => $torrent->id(),
                'editionId'      => $EditionID,
                'artists'        => $JsonArtists,
                'remastered'     => $torrent->isRemastered(),
                'remasterYear'   => $torrent->remasterYear(),
                'remasterRecordLabel'
                                 => $torrent->remasterRecordLabel() ?? '',
                'remasterCatalogueNumber'
                                 => $torrent->remasterCatalogueNumber() ?? '',
                'remasterTitle'  => $torrent->remasterTitle() ?? '',
                'media'          => $torrent->media(),
                'format'         => $torrent->format(),
                'encoding'       => $torrent->encoding(),
                'hasLog'         => $torrent->hasLog(),
                'logScore'       => $torrent->logScore(),
                'hasCue'         => $torrent->hasCue(),
                'scene'          => $torrent->isScene(),
                'vanityHouse'    => $tgroup->isShowcase(),
                'fileCount'      => $torrent->fileTotal(),
                'time'           => $torrent->uploadDate(),
                'size'           => $torrent->size(),
                'snatches'       => $torrent->snatchTotal(),
                'seeders'        => $torrent->seederTotal(),
                'leechers'       => $torrent->leecherTotal(),
                'isFreeleech'    => $torrent->isFreeleech(),
                'isNeutralLeech' => $torrent->isNeutralLeech(),
                'isPersonalFreeleech'
                                 => $torrent->isFreeleechPersonal(),
                'canUseToken'    => $Viewer->canSpendFLToken($torrent),
                'hasSnatched'    => $snatcher->showSnatch($torrent->id()),
            ];
        }

        $JsonGroups[] = [
            'groupId'       => $tgroup->id(),
            'groupName'     => $tgroup->name(),
            'artist'        => $tgroup->artistName(),
            'cover'         => $tgroup->image(),
            'tags'          => $TagList,
            'bookmarked'    => $bookmark->isTorrentBookmarked($tgroup->id()),
            'vanityHouse'   => $tgroup->isShowcase(),
            'groupYear'     => $tgroup->year(),
            'releaseType'   => $tgroup->releaseTypeName() ?? '',
            'groupTime'     => (string)$GroupTime,
            'maxSize'       => $MaxSize,
            'totalSnatched' => $tgroup->stats()->snatchTotal(),
            'totalSeeders'  => $tgroup->stats()->seedingTotal(),
            'totalLeechers' => $tgroup->stats()->leechTotal(),
            'torrents'      => $JsonTorrents,
        ];
    } else {
        // Viewing a type that does not require grouping
        $torrent = $Torrents[0];
        $JsonGroups[] = [
            'groupId'        => $tgroup->id(),
            'groupName'      => $tgroup->name(),
            'torrentId'      => $torrent->id(),
            'tags'           => $TagList,
            'category'       => $tgroup->categoryName(),
            'fileCount'      => $torrent->fileTotal(),
            'groupTime'      => $torrent->uploadDate(),
            'size'           => $torrent->size(),
            'snatches'       => $torrent->snatchTotal(),
            'seeders'        => $torrent->seederTotal(),
            'leechers'       => $torrent->leecherTotal(),
            'isFreeleech'    => $torrent->isFreeleech(),
            'isNeutralLeech' => $torrent->isNeutralLeech(),
            'isPersonalFreeleech'
                             => $torrent->isFreeleechPersonal(),
            'canUseToken'    => $Viewer->canSpendFLToken($torrent),
            'hasSnatched'    => $snatcher->showSnatch($torrent->id()),
        ];
    }
}

json_print('success', [
    'currentPage' => $Page,
    'pages' => (int)ceil($NumResults / TORRENTS_PER_PAGE),
    'results' => $JsonGroups
]);
