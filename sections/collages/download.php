<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('zip_downloader')) {
    error(403);
}

if (!isset($_REQUEST['preference']) || count($_REQUEST['list']) === 0) {
    error('No collage collector preference specified');
}

$collage = (new Gazelle\Manager\Collage())->findById((int)($_REQUEST['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}

$collector = new Gazelle\Collector\Collage($Viewer, new Gazelle\Manager\Torrent(), $collage, (int)$_REQUEST['preference']);
if (!$collector->prepare($_REQUEST['list'])) {
    error("Nothing to gather, choose some encodings and media!");
}
$Viewer->modifyOption('Collector', [implode(':', $_REQUEST['list']), $_REQUEST['preference']]);

$collector->emitZip(Gazelle\Util\Zip::make($collage->name()));
