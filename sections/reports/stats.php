<?php

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    error(403);
}

echo $Twig->render('report/stats.twig', [
    'stats'  => new Gazelle\Stats\Report,
    'viewer' => $Viewer,
]);
