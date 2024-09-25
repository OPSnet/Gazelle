<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('zip_downloader')) {
    error(403);
}

if (empty($_GET['title'])) {
    error('Collector type not specified');
}
$title = trim($_GET['title']);

switch ($title) {
    case 'better':
        $ids = array_filter(explode(',', $_GET['ids'] ?? '0'), fn($id) => (int)$id > 0);
        break;
    case 'seedbox':
        authorize();
        $user = (new Gazelle\Manager\User())->findById((int)($_GET['userid'] ?? 0));
        if (is_null($user)) {
            error(404);
        }
        $ids = (new Gazelle\User\Seedbox($user))
            ->setSource($_GET['s'] ?? '')
            ->setTarget($_GET['t'] ?? '')
            ->setUnion($_GET['m'] === 'union')
            ->idList();
        $title = "$title-" . $user->username();
        break;
    default:
        error('Unknown collector type');
}

if (!$ids) {
    error('No groups found to collect');
}

$collector = new Gazelle\Collector\TList($Viewer, new Gazelle\Manager\Torrent(), $title, 0);
$collector->setList($ids);
if (!$collector->prepare([])) {
    error("Nothing to gather, choose some encodings and bitrates!");
}

$collector->emitZip(Gazelle\Util\Zip::make($title));
