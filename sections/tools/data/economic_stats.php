<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

echo $Twig->render('admin/economy.twig', [
    'info' => new Gazelle\Stats\Economic(),
]);
