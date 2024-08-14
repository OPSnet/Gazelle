<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_debug')) {
    error(403);
}

$stats = new Gazelle\Stats\Users();

echo $Twig->render('admin/platform-usage.twig', [
    'os_list'      => $stats->operatingSystemList(),
    'browser_list' => $stats->browserList(),
]);
