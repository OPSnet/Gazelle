<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();
if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

$torrent = new Manager\Torrent()->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    Error404::error("Was not able to find torrent #" . (int)$_GET['torrentid']);
}
$riplog = new Manager\Riplog()->findById($torrent, (int)$_GET['logid']);
if (is_null($riplog)) {
    Error404::error("Was not able to find log #" . (int)$_GET['logid']);
}

$logId = $riplog->id;
$riplog->remove();
new Manager\Riplog()->refreshTorrent($torrent);
$torrent->logger()->torrent(
    $torrent,
    $Viewer,
    "Riplog ID $logId removed from torrent {$torrent->id}",
);

header("Location: {$torrent->location()}");
