<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}
$torrent = new Manager\Torrent()->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    Error404::error("Did not find a torrent with that torrentid");
}

new Manager\Riplog()->reparse($torrent, (int)$_GET['logid']);

header("Location: {$torrent->location()}");
