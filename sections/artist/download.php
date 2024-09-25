<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('zip_downloader')) {
    error(403);
}
if (!isset($_REQUEST['preference']) || count($_REQUEST['list']) === 0) {
    error('No artist collector preference specified');
}
$artist = (new Gazelle\Manager\Artist())->findById((int)($_REQUEST['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}

$collector = new Gazelle\Collector\Artist($Viewer, new Gazelle\Manager\Torrent(), $artist, (int)$_REQUEST['preference']);
if (!$collector->prepare($_REQUEST['list'])) {
    error("Nothing to gather, choose some encodings and media!");
}
$Viewer->modifyOption('Collector', [implode(':', $_REQUEST['list']), $_REQUEST['preference']]);

$collector->emitZip(Gazelle\Util\Zip::make($artist->name()));
