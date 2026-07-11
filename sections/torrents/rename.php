<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}

$name = trim($_POST['name'] ?? '');
if (empty($name)) {
    Error400::error('Torrent groups must have a name');
}

$tgMan = new \Gazelle\Manager\TGroup();
$tgroup = $tgMan->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    Error404::error();
}

$tgroup->rename($name, $Viewer);
header("Location: {$tgroup->location()}");
