<?php

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

authorize();

(new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent()))
    ->findById((int)($_POST['reportid'] ?? 0))
    ?->modifyComment($_POST['comment'] ?? '');
