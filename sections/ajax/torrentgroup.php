<?php

$GroupAllowed = ['WikiBody', 'WikiImage', 'ID', 'Name', 'Year', 'RecordLabel', 'CatalogueNumber', 'ReleaseType', 'CategoryID', 'Time', 'VanityHouse'];
$TorrentAllowed = ['ID', 'Media', 'Format', 'Encoding', 'Remastered', 'RemasterYear', 'RemasterTitle', 'RemasterRecordLabel', 'RemasterCatalogueNumber', 'Scene', 'HasLog', 'HasCue', 'LogScore', 'FileCount', 'Size', 'Seeders', 'Leechers', 'Snatched', 'FreeTorrent', 'Time', 'Description', 'FileList', 'FilePath', 'UserID', 'Username'];

$GroupID = (int)$_GET['id'];
$infohash = $_GET['hash'];
if ($GroupID && $infohash) {
    json_die("failure", "bad parameters");
}
$tgroupMan = new Gazelle\Manager\TGroup;
if ($GroupID) {
    $group = $tgroupMan->findById($GroupID);
} else if ($infohash) {
    $group = $tgroupMan->findByTorrentInfohash($infohash);
    if (!$GroupID) {
        json_die("failure", "bad hash parameter");
    }
}
if (is_null($group)) {
    json_die("failure", "bad id parameter");
}
$GroupID = $group->id();

$TorrentCache = get_group_info($GroupID, 0, true, true);
if (!$TorrentCache) {
    json_die("failure", "bad id parameter");
}
[$TorrentDetails, $TorrentList] = $TorrentCache;

$CategoryName = ($TorrentDetails['CategoryID'] == 0)
    ? "Unknown"
    : $Categories[$TorrentDetails['CategoryID'] - 1];

$Torrent = $TorrentList[$TorrentID];

$JsonTorrentDetails = [
    'wikiBody'        => Text::full_format($TorrentDetails['WikiBody']),
    'wikiBBcode'      => $TorrentDetails['WikiBody'],
    'wikiImage'       => $TorrentDetails['WikiImage'],
    'id'              => (int)$TorrentDetails['ID'],
    'name'            => $TorrentDetails['Name'],
    'year'            => (int)$TorrentDetails['Year'],
    'recordLabel'     => $TorrentDetails['RecordLabel'],
    'catalogueNumber' => $TorrentDetails['CatalogueNumber'],
    'releaseType'     => (int)$TorrentDetails['ReleaseType'],
    'categoryId'      => (int)$TorrentDetails['CategoryID'],
    'categoryName'    => $CategoryName,
    'time'            => $TorrentDetails['Time'],
    'vanityHouse'     => ($TorrentDetails['VanityHouse'] == 1),
    'isBookmarked'    => (new \Gazelle\Bookmark)->isTorrentBookmarked($LoggedUser['ID'], $GroupID),
    'musicInfo'       => ($CategoryName != "Music")
        ? []
        : Artists::get_artist_by_type($GroupID),
    'tags'            => explode('|', $TorrentDetails['tagNames']),
];

$userMan = new Gazelle\Manager\User;
$JsonTorrentList = [];
foreach ($TorrentList as $Torrent) {
    if ($Torrent['is_deleted']) {
        continue;
    }

    // Convert file list back to the old format
    $FileList = explode("\n", $Torrent['FileList']);
    foreach ($FileList as &$file) {
        $file = $torMan->apiFilename($file);
    }
    unset($file);
    $user = $userMan->findById($Torrent['UserID']);

    $JsonTorrentList[] = [
        'id'                      => (int)$Torrent['ID'],
        'media'                   => $Torrent['Media'],
        'format'                  => $Torrent['Format'],
        'encoding'                => $Torrent['Encoding'],
        'remastered'              => $Torrent['Remastered'] == 1,
        'remasterYear'            => (int)$Torrent['RemasterYear'],
        'remasterTitle'           => $Torrent['RemasterTitle'],
        'remasterRecordLabel'     => $Torrent['RemasterRecordLabel'],
        'remasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
        'scene'                   => $Torrent['Scene'] == 1,
        'hasLog'                  => $Torrent['HasLog'] == 1,
        'hasCue'                  => $Torrent['HasCue'] == 1,
        'hasLogDB'                => $Torrent['HasLogDB'] == 1,
        'logScore'                => (int)$Torrent['LogScore'],
        'logChecksum'             => $Torrent['LogChecksum'] == 1,
        'fileCount'               => (int)$Torrent['FileCount'],
        'size'                    => (int)$Torrent['Size'],
        'seeders'                 => (int)$Torrent['Seeders'],
        'leechers'                => (int)$Torrent['Leechers'],
        'snatched'                => (int)$Torrent['Snatched'],
        'freeTorrent'             => $Torrent['FreeTorrent'] == 1,
        'reported'                => count(Torrents::get_reports($Torrent['ID'])) > 0,
        'time'                    => $Torrent['Time'],
        'description'             => $Torrent['Description'],
        'fileList'                => implode('|||', $FileList),
        'filePath'                => $Torrent['FilePath'],
        'userId'                  => (int)$Torrent['UserID'],
        'username'                => $user ? $user->username() : null,
    ];
}

json_print("success", ['group' => $JsonTorrentDetails, 'torrents' => $JsonTorrentList]);
