<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

echo $Twig->render('admin/torrent-report-view.twig', [
    'list'   => (new Gazelle\Manager\Torrent\ReportType())->list(),
    'viewer' => $Viewer,
]);
