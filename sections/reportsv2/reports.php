<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_reports')) {
    error(403);
}

echo $Twig->render('reportsv2/outline.twig', [
    'viewer' => $Viewer,
]);
