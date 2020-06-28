<?php
require(__DIR__ . '/../torrents/functions.php');

$GroupAllowed = ['WikiBody', 'WikiImage', 'ID', 'Name', 'Year', 'RecordLabel', 'CatalogueNumber', 'ReleaseType', 'CategoryID', 'Time', 'VanityHouse'];
$TorrentAllowed = ['ID', 'Media', 'Format', 'Encoding', 'Remastered', 'RemasterYear', 'RemasterTitle', 'RemasterRecordLabel', 'RemasterCatalogueNumber', 'Scene', 'HasLog', 'HasCue', 'LogScore', 'FileCount', 'Size', 'Seeders', 'Leechers', 'Snatched', 'FreeTorrent', 'Time', 'Description', 'FileList', 'FilePath', 'UserID', 'Username'];

$TorrentID = (int)$_GET['id'];
$TorrentHash = (string)$_GET['hash'];

if ($TorrentID && $TorrentHash) {
    json_die("failure", "bad parameters");
}

$torMan = new \Gazelle\Manager\Torrent;

if ($TorrentHash) {
    if (!$torMan->isValidHash($TorrentHash)) {
        json_die("failure", "bad hash parameter");
    } else {
        $TorrentID = $torMan->hashToTorrentId($TorrentHash);
        if (!$TorrentID) {
            json_die("failure", "bad hash parameter");
        }
    }
}

if ($TorrentID <= 0) {
    json_die("failure", "bad id parameter");
}

$TorrentCache = get_torrent_info($TorrentID, 0, true, true);

if (!$TorrentCache) {
    json_die("failure", "bad id parameter");
}

list($TorrentDetails, $TorrentList) = $TorrentCache;

if (!isset($TorrentList[$TorrentID])) {
    json_die("failure", "bad id parameter");
}

$GroupID = $TorrentDetails['ID'];

$CategoryName = ($TorrentDetails['CategoryID'] == 0)
    ? "Unknown"
    : $Categories[$TorrentDetails['CategoryID'] - 1];

$bookmark = new \Gazelle\Bookmark;
$JsonTorrentDetails = [
    'wikiBody'        => Text::full_format($TorrentDetails['WikiBody']),
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
    'vanityHouse'     => $TorrentDetails['VanityHouse'] == 1,
    'isBookmarked'    => $bookmark->isTorrentBookmarked($LoggedUser['ID'], $GroupID),
    'tags'            => explode('|', $TorrentDetails['tagNames']),
    'musicInfo'       => ($CategoryName != "Music")
        ? null : Artists::get_artist_by_type($GroupID),
];

$Torrent = $TorrentList[$TorrentID];

// Convert file list back to the old format
$FileList = explode("\n", $Torrent['FileList']);
foreach ($FileList as &$File) {
    $File = Torrents::filelist_old_format($File);
}
unset($File);
$Username = Users::user_info($Torrent['UserID'])['Username'];

$JsonTorrentList[] = array_merge(
    $TorrentHash
        ? [ 'infoHash' => $Torrent['InfoHash'] ]
        : [],
    [
        'id' => (int)$Torrent['ID'],
        'media' => $Torrent['Media'],
        'format' => $Torrent['Format'],
        'encoding' => $Torrent['Encoding'],
        'remastered' => $Torrent['Remastered'] == 1,
        'remasterYear' => (int)$Torrent['RemasterYear'],
        'remasterTitle' => $Torrent['RemasterTitle'],
        'remasterRecordLabel' => $Torrent['RemasterRecordLabel'],
        'remasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
        'scene' => $Torrent['Scene'] == 1,
        'hasLog' => $Torrent['HasLog'] == 1,
        'hasCue' => $Torrent['HasCue'] == 1,
        'logScore' => (int)$Torrent['LogScore'],
        'logChecksum' => (int)$Torrent['LogChecksum'],
        'logCount' => (int)$Torrent['LogCount'],
        'fileCount' => (int)$Torrent['FileCount'],
        'size' => (int)$Torrent['Size'],
        'seeders' => (int)$Torrent['Seeders'],
        'leechers' => (int)$Torrent['Leechers'],
        'snatched' => (int)$Torrent['Snatched'],
        'freeTorrent' => $Torrent['FreeTorrent'] == 1,
        'reported' => count(Torrents::get_reports($TorrentID)) > 0,
        'time' => $Torrent['Time'],
        'description' => $Torrent['Description'],
        'fileList' => implode('|||', $FileList),
        'filePath' => $Torrent['FilePath'],
        'userId' => (int)$Torrent['UserID'],
        'username' => $Username,
    ]
);

json_print("success", ['group' => $JsonTorrentDetails, 'torrent' => array_pop($JsonTorrentList)]);
