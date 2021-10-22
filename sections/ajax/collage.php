<?php

$CollageID = (int)$_GET['id'];
if (!$CollageID) {
    json_die("failure", "bad parameters");
}

$CacheKey = sprintf(\Gazelle\Collage::CACHE_KEY, $CollageID);
$CollageData = $Cache->get_value($CacheKey);
if ($CollageData) {
    [$Name, $Description, $CommentList, $Deleted, $CollageCategoryID, $CreatorID, $Locked, $MaxGroups, $MaxGroupsPerUser, $Updated, $Subscribers] = $CollageData;
} else {
    [$Name, $Description, $CreatorID, $Deleted, $CollageCategoryID, $Locked, $MaxGroups, $MaxGroupsPerUser, $Updated, $Subscribers]
    = $DB->row("
        SELECT
            Name,
            Description,
            UserID,
            Deleted,
            CategoryID,
            Locked,
            MaxGroups,
            MaxGroupsPerUser,
            Updated,
            Subscribers
        FROM collages
        WHERE ID = ?
        ", $CollageID
    );
    if (!$Name) {
        json_die("failure");
    }
    $CommentList = null;
    $SetCache = true;
}

$torMan = new Gazelle\Manager\Torrent;

// TODO: Cache this
$DB->prepared_query("
    SELECT GroupID
    FROM collages_torrents
    WHERE CollageID = ?
    ", $CollageID
);
$TorrentGroups = $DB->collect('GroupID');

$bookmark = new \Gazelle\Bookmark;
$JSON = [
    'id'                  => (int)$CollageID,
    'name'                => $Name,
    'description'         => Text::full_format($Description),
    'creatorID'           => (int)$CreatorID,
    'deleted'             => (bool)$Deleted,
    'collageCategoryID'   => (int)$CollageCategoryID,
    'collageCategoryName' => COLLAGE[(int)$CollageCategoryID],
    'locked'              => (bool)$Locked,
    'maxGroups'           => (int)$MaxGroups,
    'maxGroupsPerUser'    => (int)$MaxGroupsPerUser,
    'hasBookmarked'       => $bookmark->isCollageBookmarked($Viewer->id(), $CollageID),
    'subscriberCount'     => (int)$Subscribers,
    'torrentGroupIDList'  => $TorrentGroups
];

if ($CollageCategoryID != COLLAGE_ARTISTS_ID) {
    // torrent collage
    $TorrentGroups = [];
    $DB->prepared_query("
        SELECT
            ct.GroupID
        FROM collages_torrents AS ct
        INNER JOIN torrents_group AS tg ON (tg.ID = ct.GroupID)
        WHERE ct.CollageID = ?
        ORDER BY ct.Sort
        ", $CollageID
    );
    $GroupIDs = $DB->collect('GroupID');
    $GroupList = Torrents::get_groups($GroupIDs);
    foreach ($GroupIDs as $GroupID) {
        if (!empty($GroupList[$GroupID])) {
            $GroupDetails = Torrents::array_group($GroupList[$GroupID]);
            if ($GroupDetails['GroupCategoryID'] > 0 && CATEGORY[$GroupDetails['GroupCategoryID'] - 1] == 'Music') {
                $JsonMusicInfo = Artists::get_artist_by_type($GroupID);
            } else {
                $JsonMusicInfo = null;
            }
            $TorrentList = [];
            foreach ($GroupDetails['Torrents'] as $Torrent) {
                $TorrentList[] = [
                    'torrentid'               => (int)$Torrent['ID'],
                    'media'                   => $Torrent['Media'],
                    'format'                  => $Torrent['Format'],
                    'encoding'                => $Torrent['Encoding'],
                    'remastered'              => ($Torrent['Remastered'] == 1),
                    'remasterYear'            => (int)$Torrent['RemasterYear'],
                    'remasterTitle'           => $Torrent['RemasterTitle'],
                    'remasterRecordLabel'     => $Torrent['RemasterRecordLabel'],
                    'remasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
                    'scene'                   => ($Torrent['Scene'] == 1),
                    'hasLog'                  => ($Torrent['HasLog'] == 1),
                    'hasCue'                  => ($Torrent['HasCue'] == 1),
                    'logScore'                => (int)$Torrent['LogScore'],
                    'fileCount'               => (int)$Torrent['FileCount'],
                    'size'                    => (int)$Torrent['Size'],
                    'seeders'                 => (int)$Torrent['Seeders'],
                    'leechers'                => (int)$Torrent['Leechers'],
                    'snatched'                => (int)$Torrent['Snatched'],
                    'freeTorrent'             => ($Torrent['FreeTorrent'] == 1),
                    'reported'                => $torMan->hasReport($Viewer, (int)$Torrent['ID']),
                    'time'                    => $Torrent['Time']
                ];
            }
            $TorrentGroups[] = [
                'id'              => $GroupDetails['GroupID'],
                'name'            => $GroupDetails['GroupName'],
                'year'            => $GroupDetails['GroupYear'],
                'categoryId'      => $GroupDetails['GroupCategoryID'],
                'recordLabel'     => $GroupDetails['GroupRecordLabel'],
                'catalogueNumber' => $GroupDetails['GroupCatalogueNumber'],
                'vanityHouse'     => $GroupDetails['GroupVanityHouse'],
                'tagList'         => $GroupDetails['TagList'],
                'releaseType'     => $GroupDetails['ReleaseType'],
                'wikiImage'       => $GroupDetails['WikiImage'],
                'musicInfo'       => $JsonMusicInfo,
                'torrents'        => $TorrentList
            ];
        }
    }
    $JSON['torrentgroups'] = $TorrentGroups;
} else {
    // artist collage
    $DB->prepared_query("
        SELECT
            ca.ArtistID,
            ag.Name,
            aw.Image
        FROM collages_artists AS ca
        INNER JOIN artists_group AS ag ON (ag.ArtistID = ca.ArtistID)
        LEFT JOIN wiki_artists AS aw ON (aw.RevisionID = ag.RevisionID)
        WHERE ca.CollageID = ?
        ORDER BY ca.Sort
        ", $CollageID
    );
    $Artists = [];
    while ([$ArtistID, $ArtistName, $ArtistImage] = $DB->next_record()) {
        $Artists[] = [
            'id'    => (int)$ArtistID,
            'name'  => $ArtistName,
            'image' => $ArtistImage
        ];
    }
    $JSON['artists'] = $Artists;
}

if (isset($SetCache)) {
    $CollageData = [
        $Name,
        $Description,
        $CommentList,
        (bool)$Deleted,
        (int)$CollageCategoryID,
        (int)$CreatorID,
        (bool)$Locked,
        (int)$MaxGroups,
        (int)$MaxGroupsPerUser,
        $Updated,
        (int)$Subscribers];
    $Cache->cache_value($CacheKey, $CollageData, 3600);
}

json_print("success", $JSON);
