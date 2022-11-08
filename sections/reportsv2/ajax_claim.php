<?php

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

echo (new Gazelle\Manager\Torrent\Report)
    ->findById((int)($_GET['id'] ?? 0))
    ?->claim($Viewer->id()) ?? 0;
