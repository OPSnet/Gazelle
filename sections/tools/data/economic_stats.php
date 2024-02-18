<?php

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

echo $Twig->render('admin/economy.twig', [
    'info' => new Gazelle\Stats\Economic(),
]);
