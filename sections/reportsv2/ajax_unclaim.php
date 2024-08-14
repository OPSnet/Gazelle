<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

(new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent()))
    ->findById((int)($_GET['id'] ?? 0))
    ?->unclaim();
