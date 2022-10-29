<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$manager = new Gazelle\Manager\Torrent\ReportType;

echo $Twig->render('admin/torrent-report-view.twig', [
    'list'   => $manager->list(),
    'viewer' => $Viewer,
]);
