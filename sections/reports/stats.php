<?php

if (!$Viewer->permitted('admin_reports') && !$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

echo $Twig->render('report/stats.twig', [
    'stats'  => new Gazelle\Stats\Report,
    'viewer' => $Viewer,
]);
