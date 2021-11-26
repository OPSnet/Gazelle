<?php

if (!$Viewer->permitted('site_analysis')) {
    error(403);
}

$case = (new Gazelle\Manager\ErrorLog)->findById((int)($_GET['case'] ?? 0));
if (is_null($case)) {
    error(404);
}

echo $Twig->render('debug/analysis.twig', [
    'case'   => $case,
    'viewer' => $Viewer,
]);
