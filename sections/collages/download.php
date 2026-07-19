<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('zip_downloader')) {
    Error403::error();
}

if (!isset($_REQUEST['preference']) || count($_REQUEST['list']) === 0) {
    Error400::error('No collage collector preference specified');
}

$collage = new Manager\Collage()->findById((int)($_REQUEST['collageid'] ?? 0));
if (is_null($collage)) {
    Error404::error();
}

$collector = new Collector\Collage($Viewer, new Manager\Torrent(), $collage, (int)$_REQUEST['preference']);
if (!$collector->prepare($_REQUEST['list'])) {
    Error400::error("Nothing to gather, choose some encodings and media!");
}
$Viewer->modifyOption('Collector', [implode(':', $_REQUEST['list']), $_REQUEST['preference']]);

$collector->emitZip(Util\Zip::make($collage->name()));
