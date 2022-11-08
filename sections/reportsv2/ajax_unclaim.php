<?php

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

(new Gazelle\Manager\Torrent\Report)
    ->findById((int)($_GET['id'] ?? 0))
    ?->unclaim();
