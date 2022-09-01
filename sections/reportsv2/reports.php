<?php

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

echo $Twig->render('reportsv2/outline.twig', [
    'viewer' => $Viewer,
]);
