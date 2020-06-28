<?php
//For sorting tags
$OnlyArtistReleases = !empty($_GET['artistreleases']);

if ($_GET['id'] && $_GET['artistname']) {
    json_die("failure", "bad parameters");
}

$ArtistID = $_GET['id'];
if ($ArtistID && !is_number($ArtistID)) {
    json_die("failure");
}

if (empty($ArtistID)) {
    if (!empty($_GET['artistname'])) {
        $DB->prepared_query('
            SELECT ArtistID
            FROM artists_alias
            WHERE Name LIKE ?
            LIMIT 1
            ', trim($_GET['artistname'])
        );
        if (!(list($ArtistID) = $DB->next_record(MYSQLI_NUM, false))) {
            json_die("failure");
        }
        // If we get here, we got the ID!
    }
}


if (!empty($_GET['revisionid'])) { // if they're viewing an old revision
    $RevisionID = $_GET['revisionid'];
    if (!is_number($RevisionID)) {
        json_die("failure", "bad parameters");
    }
} else { // viewing the live version
    $RevisionID = false;
}

$Artist = new \Gazelle\Artist($ArtistID, $Revision);
$cacheKey = $Artist->cacheKey();
$Data = $Cache->get_value($cacheKey);
if ($Data) {
    list($Name, $Image, $Body, $VanityHouseArtist, $SimilarArray) = $Data;
} else {
    $sql = 'SELECT ag.Name, wa.Image, wa.body, ag.VanityHouse
        FROM artists_group AS ag
        LEFT JOIN wiki_artists AS wa USING (RevisionID)
        WHERE ';
    if ($RevisionID) {
        $sql .= 'wa.RevisionID = ?';
        $queryId = $RevisionID;
    } else {
        $sql .= 'ag.ArtistID = ?';
        $queryId = $ArtistID;
    }

    $DB->prepared_query($sql, $queryId);
    if (!$DB->has_results()) {
        json_die("failure");
    }
    list($Name, $Image, $Body, $VanityHouseArtist)
        = $DB->next_record(MYSQLI_NUM);

    $DB->prepared_query('
        SELECT
            s2.ArtistID,
            a.Name,
            ass.Score,
            ass.SimilarID
        FROM artists_similar AS s1
        INNER JOIN artists_similar AS s2 ON (s1.SimilarID = s2.SimilarID AND s1.ArtistID != s2.ArtistID)
        INNER JOIN artists_similar_scores AS ass ON (ass.SimilarID = s1.SimilarID)
        INNER JOIN artists_group AS a ON (a.ArtistID = s2.ArtistID)
        WHERE s1.ArtistID = ?
        ORDER BY ass.Score DESC
        LIMIT 30
        ', $ArtistID
    );
    $SimilarArray = $DB->to_array();
    $Cache->cache_value($cacheKey,
        [$Name, $Image, $Body, $VanityHouseArtist, $SimilarArray], 3600);
}

// Requests
$Requests = $LoggedUser['DisableRequests'] ? [] : $Artist->requests();
$NumRequests = count($Requests);

if (($Importances = $Cache->get_value("artist_groups_$ArtistID")) === false) {
    $DB->prepared_query('
        SELECT
            DISTINCTROW ta.GroupID, ta.Importance, tg.VanityHouse, tg.Year
        FROM torrents_artists AS ta
        INNER JOIN torrents_group AS tg ON (tg.ID = ta.GroupID)
        WHERE ta.ArtistID = ?
        ORDER BY tg.Year DESC, tg.Name DESC
        ', $ArtistID
    );
    $GroupIDs = $DB->collect('GroupID');
    $Importances = $DB->to_array(false, MYSQLI_BOTH, false);
    $Cache->cache_value("artist_groups_$ArtistID", $Importances, 0);
} else {
    $GroupIDs = [];
    foreach ($Importances as $Group) {
        $GroupIDs[] = $Group['GroupID'];
    }
}
if (count($GroupIDs) > 0) {
    $TorrentList = Torrents::get_groups($GroupIDs, true, true);
} else {
    $TorrentList = [];
}
$NumGroups = count($TorrentList);

//Get list of used release types
$UsedReleases = [];
foreach ($TorrentList as $GroupID=>$Group) {
    if ($Importances[$GroupID]['Importance'] == '2') {
        $TorrentList[$GroupID]['ReleaseType'] = 1024;
        $GuestAlbums = true;
    }
    if ($Importances[$GroupID]['Importance'] == '3') {
        $TorrentList[$GroupID]['ReleaseType'] = 1023;
        $RemixerAlbums = true;
    }
    if ($Importances[$GroupID]['Importance'] == '4') {
        $TorrentList[$GroupID]['ReleaseType'] = 1022;
        $ComposerAlbums = true;
    }
    if ($Importances[$GroupID]['Importance'] == '7') {
        $TorrentList[$GroupID]['ReleaseType'] = 1021;
        $ProducerAlbums = true;
    }
    if (!in_array($TorrentList[$GroupID]['ReleaseType'], $UsedReleases)) {
        $UsedReleases[] = $TorrentList[$GroupID]['ReleaseType'];
    }
}

if (!empty($GuestAlbums)) {
    $ReleaseTypes[1024] = 'Guest Appearance';
}
if (!empty($RemixerAlbums)) {
    $ReleaseTypes[1023] = 'Remixed By';
}
if (!empty($ComposerAlbums)) {
    $ReleaseTypes[1022] = 'Composition';
}
if (!empty($ProducerAlbums)) {
    $ReleaseTypes[1021] = 'Produced By';
}

reset($TorrentList);

$JsonTorrents = [];
$Tags = [];
$NumTorrents = $NumSeeders = $NumLeechers = $NumSnatches = 0;
$bookmark = new \Gazelle\Bookmark;
foreach ($GroupIDs as $GroupID) {
    if (!isset($TorrentList[$GroupID])) {
        continue;
    }
    $Group = $TorrentList[$GroupID];

    $Torrents = isset($Group['Torrents']) ? $Group['Torrents'] : [];
    $Artists = $Group['Artists'];
    $ExtendedArtists = $Group['ExtendedArtists'];

    foreach ($Artists as &$Artist) {
        $Artist['id'] = (int)$Artist['id'];
        $Artist['aliasid'] = (int)$Artist['aliasid'];
    }

    foreach ($ExtendedArtists as &$ArtistGroup) {
        foreach ($ArtistGroup as &$Artist) {
            $Artist['id'] = (int)$Artist['id'];
            $Artist['aliasid'] = (int)$Artist['aliasid'];
        }
    }

    $Found = Misc::search_array($Artists, 'id', $ArtistID);
    if ($OnlyArtistReleases && empty($Found)) {
        continue;
    }

    // $GroupVanityHouse = $Group['VanityHouse'];
    $GroupVanityHouse = $Importances[$GroupID]['VanityHouse'];

    $TagList = explode(' ',str_replace('_', '.', $Group['TagList']));

    // $Tags array is for the sidebar on the right
    foreach ($TagList as $Tag) {
        if (!isset($Tags[$Tag])) {
            $Tags[$Tag] = ['name' => $Tag, 'count' => 1];
        } else {
            $Tags[$Tag]['count']++;
        }
    }
    $InnerTorrents = [];
    foreach ($Torrents as $Torrent) {
        $NumTorrents++;
        $NumSeeders += $Torrent['Seeders'];
        $NumLeechers += $Torrent['Leechers'];
        $NumSnatches += $Torrent['Snatched'];

        $InnerTorrents[] = [
            'id' => (int)$Torrent['ID'],
            'groupId' => (int)$Torrent['GroupID'],
            'media' => $Torrent['Media'],
            'format' => $Torrent['Format'],
            'encoding' => $Torrent['Encoding'],
            'remasterYear' => (int)$Torrent['RemasterYear'],
            'remastered' => $Torrent['Remastered'] == 1,
            'remasterTitle' => $Torrent['RemasterTitle'],
            'remasterRecordLabel' => $Torrent['RemasterRecordLabel'],
            'scene' => $Torrent['Scene'] == 1,
            'hasLog' => $Torrent['HasLog'] == 1,
            'hasCue' => $Torrent['HasCue'] == 1,
            'logScore' => (int)$Torrent['LogScore'],
            'fileCount' => (int)$Torrent['FileCount'],
            'freeTorrent' => $Torrent['FreeTorrent'] == 1,
            'size' => (int)$Torrent['Size'],
            'leechers' => (int)$Torrent['Leechers'],
            'seeders' => (int)$Torrent['Seeders'],
            'snatched' => (int)$Torrent['Snatched'],
            'time' => $Torrent['Time'],
            'hasFile' => (int)$Torrent['HasFile']
        ];
    }
    $JsonTorrents[] = [
        'groupId' => (int)$GroupID,
        'groupName' => $Group['Name'],
        'groupYear' => (int)$Group['Year'],
        'groupRecordLabel' => $Group['RecordLabel'],
        'groupCatalogueNumber' => $Group['CatalogueNumber'],
        'groupCategoryID' => $Group['CategoryID'],
        'tags' => $TagList,
        'releaseType' => (int)$Group['ReleaseType'],
        'wikiImage' => $Group['WikiImage'],
        'groupVanityHouse' => $GroupVanityHouse == 1,
        'hasBookmarked' => $bookmark->isTorrentBookmarked($LoggedUser['ID'], $GroupID),
        'artists' => $Artists,
        'extendedArtists' => $ExtendedArtists,
        'torrent' => $InnerTorrents,
    ];
}

$JsonSimilar = [];
foreach ($SimilarArray as $Similar) {
    $JsonSimilar[] = [
        'artistId' => (int)$Similar['ArtistID'],
        'name' => $Similar['Name'],
        'score' => (int)$Similar['Score'],
        'similarId' => (int)$Similar['SimilarID']
    ];
}

$JsonRequests = [];
foreach ($Requests as $RequestID => $Request) {
    $JsonRequests[] = [
        'requestId' => (int)$RequestID,
        'categoryId' => (int)$Request['CategoryID'],
        'title' => $Request['Title'],
        'year' => (int)$Request['Year'],
        'timeAdded' => $Request['TimeAdded'],
        'votes' => (int)$Request['Votes'],
        'bounty' => (int)$Request['Bounty']
    ];
}

//notifications disabled by default
$notificationsEnabled = false;
if (check_perms('site_torrents_notify')) {
    if (($Notify = $Cache->get_value('notify_artists_'.$LoggedUser['ID'])) === false) {
        $DB->prepared_query('
            SELECT ID, Artists
            FROM users_notify_filters
            WHERE UserID = ?
                AND Label = ?
            LIMIT 1
            ', $LoggedUser[ID], 'Artist notifications'
        );
        $Notify = $DB->next_record(MYSQLI_ASSOC, false);
        $Cache->cache_value('notify_artists_'.$LoggedUser['ID'], $Notify, 0);
    }
    if (stripos($Notify['Artists'], "|$Name|") === false) {
        $notificationsEnabled = false;
    } else {
        $notificationsEnabled = true;
    }
}

json_print("success", [
    'id' => (int)$ArtistID,
    'name' => $Name,
    'notificationsEnabled' => $notificationsEnabled,
    'hasBookmarked' => $bookmark->isArtistBookmarked($LoggedUser['ID'], $ArtistID),
    'image' => $Image,
    'body' => Text::full_format($Body),
    'vanityHouse' => $VanityHouseArtist == 1,
    'tags' => array_values($Tags),
    'similarArtists' => $JsonSimilar,
    'statistics' => [
        'numGroups' => $NumGroups,
        'numTorrents' => $NumTorrents,
        'numSeeders' => $NumSeeders,
        'numLeechers' => $NumLeechers,
        'numSnatches' => $NumSnatches
    ],
    'torrentgroup' => $JsonTorrents,
    'requests' => $JsonRequests
]);
