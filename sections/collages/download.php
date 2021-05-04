<?php

$Viewer = new Gazelle\User($LoggedUser['ID']);
if (!$Viewer->permitted('zip_downloader')) {
    error(403);
}

if (!isset($_REQUEST['preference']) || count($_REQUEST['list']) === 0) {
    error(0);
}

$collage = (new Gazelle\Manager\Collage)->findById((int)($_REQUEST['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}

$collector = new Gazelle\Collector\Collage($Viewer, $collage, (int)$_REQUEST['preference']);
if (!$collector->prepare($_REQUEST['list'])) {
    error("Nothing to gather, choose some encodings and bitrates!");
}
$Viewer->modifyCollectorDefaults([implode(':', $_REQUEST['list']), $_REQUEST['preference']]);

header('X-Accel-Buffering: no');
$collector->emit();
