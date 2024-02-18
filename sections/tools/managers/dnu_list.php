<?php

if (!$Viewer->permitted('admin_dnu')) {
    error(403);
}

echo $Twig->render('admin/dnu.twig', [
    'auth' => $Viewer->auth(),
    'list' => (new Gazelle\Manager\DNU())->dnuList(),
]);
