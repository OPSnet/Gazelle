<?php

if (!$Viewer->permitted('admin_whitelist')) {
    error(403);
}

echo $Twig->render('admin/client-whitelist.twig', [
    'auth' => $Viewer->auth(),
    'list' => (new Gazelle\Manager\ClientWhitelist())->list(),
]);
