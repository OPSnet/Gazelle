<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

$torrent = new Manager\Torrent()->findById((int)($_REQUEST['torrentid'] ?? 0));
if (is_null($torrent)) {
    Error404::error();
}
$torrent->regenerateFilelist(
    new \OrpheusNET\BencodeTorrent\BencodeTorrent(),
);

header("Location: " . $torrent->location());
