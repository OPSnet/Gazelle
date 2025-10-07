<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (isset($_GET['id']) && isset($_GET['hash'])) {
    json_error('bad parameters');
} elseif (isset($_GET['hash'])) {
    $torrent = new Manager\Torrent()->findByInfohash($_GET['hash'] ?? '');
} else {
    $torrent = new Manager\Torrent()->findById((int)$_GET['id']);
}
if (is_null($torrent)) {
    json_error('bad parameters');
}

echo new Json\Torrent($torrent, $Viewer)
    ->setVersion(5)
    ->response();
