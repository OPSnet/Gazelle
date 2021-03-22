<?php

if (!check_perms('zip_downloader')) {
    error(403);
}
$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
$viewer = new Gazelle\User($LoggedUser['ID']);

switch ($_GET['type']) {
    case 'uploads':
        if (!$user->propertyVisible($viewer, 'uploads')) {
            error(403);
        }
        $SQL = "WHERE t.UserID = ?";
        $Month = "t.Time";
        break;
    case 'snatches':
        if (!$user->propertyVisible($viewer, 'snatched')) {
            error(403);
        }
        $SQL = "
            INNER JOIN xbt_snatched AS x ON (t.ID = x.fid)
            WHERE x.uid = ?";
        $Month = "FROM_UNIXTIME(x.tstamp)";
        break;
    case 'seeding':
        if (!$user->propertyVisible($viewer, 'seeding')) {
            error(403);
        }
        $SQL = "
            INNER JOIN xbt_files_users AS xfu ON (t.ID = xfu.fid)
            WHERE xfu.remaining = 0
                AND xfu.uid = ?";
        $Month = "FROM_UNIXTIME(xfu.mtime)";
        break;
    default:
        error(0);
}

$DownloadsQ = $DB->prepared_query("
    SELECT
        t.ID AS TorrentID,
        DATE_FORMAT($Month, '%Y - %m') AS Month,
        t.GroupID,
        t.Media,
        t.Format,
        t.Encoding,
        IF(t.RemasterYear = 0, tg.Year, t.RemasterYear) AS Year,
        tg.Name,
        t.Size
    FROM torrents AS t
    INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    $SQL
    GROUP BY TorrentID
    ", $user->id()
);

$Collector = new TorrentsDL($DownloadsQ, $user->username() . "'s ".ucfirst($_GET['type']));
$filer = new \Gazelle\File\Torrent;

while ([$Downloads, $GroupIDs] = $Collector->get_downloads('TorrentID')) {
    $Artists = Artists::get_artists($GroupIDs);
    $TorrentIDs = array_keys($GroupIDs);
    $TorrentFilesQ = $DB->prepared_query("
        SELECT ID FROM torrents WHERE ID IN (" . placeholders($TorrentIDs) . ")
        ", ...$TorrentIDs
    );
    if (is_int($TorrentFilesQ)) {
        // Query failed. Let's not create a broken zip archive
        foreach ($TorrentIDs as $TorrentID) {
            $Download =& $Downloads[$TorrentID];
            $Download['Artist'] = Artists::display_artists($Artists[$Download['GroupID']], false, true, false);
            $Collector->fail_file($Download);
        }
        continue;
    }
    while ([$TorrentID] = $DB->next_record(MYSQLI_NUM, false)) {
        $Download =& $Downloads[$TorrentID];
        $Download['Artist'] = Artists::display_artists($Artists[$Download['GroupID']], false, true, false);
        $Collector->add_file($filer->get($TorrentID), $Download, $Download['Month']);
        unset($Download);
    }
}
$Collector->finalize(false);

define('SKIP_NO_CACHE_HEADERS', 1);
