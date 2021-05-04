<?php

$Viewer = new Gazelle\User($LoggedUser['ID']);
if (!$Viewer->permitted('zip_downloader')) {
    error(403);
}

if (empty($_GET['title'])) {
    error(0);
}
$title = trim($_GET['title']);

switch ($title) {
    case 'better':
        $ids = array_filter(explode(',', $_GET['ids'] ?? '0'), function ($id) {return (int)$id > 0;});
        break;
    case 'seedbox':
        authorize();
        $user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
        if (is_null($user)) {
            error(404);
        }
        $ids = (new Gazelle\Seedbox($user->id()))
            ->setSource($_GET['s'] ?? '')
            ->setTarget($_GET['t'] ?? '')
            ->setUnion($_GET['m'] === 'union')
            ->idList();
        $title = "$title-" . $user->username();
        break;
    default:
        error(0);
}

if (!$ids) {
    error(0);
}

$collector = new Gazelle\Collector\TList($Viewer, $title, 0);
$collector->setList($ids);
if (!$collector->prepare([])) {
    error("Nothing to gather, choose some encodings and bitrates!");
}

header('X-Accel-Buffering: no');
$collector->emit();
