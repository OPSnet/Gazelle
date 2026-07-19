<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_reports')) {
    Error403::error();
}

authorize();

new Manager\Torrent\Report(new Manager\Torrent())
    ->findById((int)($_POST['reportid'] ?? 0))
    ?->modifyComment($_POST['comment'] ?? '');
