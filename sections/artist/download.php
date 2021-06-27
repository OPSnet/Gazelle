<?php

if (!$Viewer->permitted('zip_downloader')) {
    error(403);
}
if (!isset($_REQUEST['preference']) || count($_REQUEST['list']) === 0) {
    error(0);
}
$artist = (new Gazelle\Manager\Artist)->findById((int)($_REQUEST['artistid'] ?? 0), 0);
if (is_null($artist)) {
    error(404);
}

$collector = new Gazelle\Collector\Artist($Viewer, $artist, (int)$_REQUEST['preference']);
if (!$collector->prepare($_REQUEST['list'])) {
    error("Nothing to gather, choose some encodings and bitrates!");
}
$Viewer->modifyCollectorDefaults([implode(':', $_REQUEST['list']), $_REQUEST['preference']]);

header('X-Accel-Buffering: no');
$collector->emit();
