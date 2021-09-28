<?php

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

echo $Twig->render('admin/stats/torrent.twig', [
    'stats' => new Gazelle\Stats\Torrent,
]);
