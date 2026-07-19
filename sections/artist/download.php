<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('zip_downloader')) {
    Error403::error();
}
if (!isset($_REQUEST['preference']) || count($_REQUEST['list']) === 0) {
    Error400::error('No artist collector preference specified');
}
$artist = new Manager\Artist()->findById((int)($_REQUEST['artistid'] ?? 0));
if (is_null($artist)) {
    Error404::error();
}

$collector = new Collector\Artist($Viewer, new Manager\Torrent(), $artist, (int)$_REQUEST['preference']);
if (!$collector->prepare($_REQUEST['list'])) {
    Error400::error("Nothing to gather, choose some encodings and media!");
}
$Viewer->modifyOption('Collector', [implode(':', $_REQUEST['list']), $_REQUEST['preference']]);

$collector->emitZip(Util\Zip::make($artist->name()));
