<?php

ini_set('memory_limit', -1);

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
}

$JsonBookmarks = [];

[$GroupIDs, , $GroupList] = $user->bookmarkList();

foreach($GroupIDs as $GroupID) {
    if (!isset($GroupList[$GroupID])) {
        continue;
    }
    $Group = $GroupList[$GroupID];
    $JsonTorrents = [];
    foreach ($Group['Torrents'] as $Torrent) {
        $JsonTorrents[] = [
            'id' => (int)$Torrent['ID'],
            'groupId' => (int)$Torrent['GroupID'],
            'media' => $Torrent['Media'],
            'format' => $Torrent['Format'],
            'encoding' => $Torrent['Encoding'],
            'remasterYear' => (int)$Torrent['RemasterYear'],
            'remastered' => $Torrent['Remastered'] == 1,
            'remasterTitle' => $Torrent['RemasterTitle'],
            'remasterRecordLabel' => $Torrent['RemasterRecordLabel'],
            'remasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
            'scene' => $Torrent['Scene'] == 1,
            'hasLog' => $Torrent['HasLog'] == 1,
            'hasCue' => $Torrent['HasCue'] == 1,
            'logScore' => (float)$Torrent['LogScore'],
            'fileCount' => (int)$Torrent['FileCount'],
            'freeTorrent' => $Torrent['FreeTorrent'] == 1,
            'size' => (float)$Torrent['Size'],
            'leechers' => (int)$Torrent['Leechers'],
            'seeders' => (int)$Torrent['Seeders'],
            'snatched' => (int)$Torrent['Snatched'],
            'time' => $Torrent['Time'],
            'hasFile' => (int)$Torrent['HasFile']
        ];
    }
    $JsonBookmarks[] = [
        'id' => (int)$Group['ID'],
        'name' => $Group['Name'],
        'year' => (int)$Group['Year'],
        'recordLabel' => $Group['RecordLabel'],
        'catalogueNumber' => $Group['CatalogueNumber'],
        'tagList' => $Group['TagList'],
        'releaseType' => $Group['ReleaseType'],
        'vanityHouse' => $Group['VanityHouse'] == 1,
        'image' => $Group['WikiImage'],
        'torrents' => $JsonTorrents
    ];
}

print json_encode([
    'status' => 'success',
    'response' => [
        'bookmarks' => $JsonBookmarks
    ]
]);
