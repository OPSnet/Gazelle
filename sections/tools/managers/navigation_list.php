<?php

if (!$Viewer->permitted('admin_manage_navigation')) {
    error(403);
}

echo $Twig->render('admin/user-navigation.twig', [
    'auth' => $Viewer->auth(),
    'list' => (new Gazelle\Manager\UserNavigation())->fullList(),
]);
