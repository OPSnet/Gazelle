<?php

if (!$Viewer->permitted('zip_downloader')) {
    error(403);
}
$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}

switch ($_GET['type']) {
    case 'uploads':
        if (!$user->propertyVisible($Viewer, 'uploads')) {
            error(403);
        }
        $SQL = "WHERE t.UserID = ?";
        $label = 'uploaded';
        break;
    case 'snatches':
        if (!$user->propertyVisible($Viewer, 'snatched')) {
            error(403);
        }
        $SQL = "
            INNER JOIN xbt_snatched AS x ON (t.ID = x.fid)
            WHERE x.uid = ?";
        $label = 'snatched';
        break;
    case 'seeding':
        if (!$user->propertyVisible($Viewer, 'seeding')) {
            error(403);
        }
        $SQL = "
            INNER JOIN xbt_files_users AS xfu ON (t.ID = xfu.fid)
            WHERE xfu.remaining = 0
                AND xfu.uid = ?";
        $label = 'seeding';
        break;
    default:
        error(0);
}

$title = "{$user->username()}-$label";
$collector = new Gazelle\Collector\TList($Viewer, new Gazelle\Manager\Torrent, $title, 0);

$db = Gazelle\DB::DB();
$db->prepared_query("
    SELECT DISTINCT t.ID
    FROM torrents AS t
    INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    $SQL
    ", $user->id()
);
$collector->setList($db->collect(0, false));
$collector->prepare([]);

$collector->emitZip(Gazelle\Util\Zip::make($title));
