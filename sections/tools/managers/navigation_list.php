<?php

if (!$Viewer->permitted('admin_manage_navigation')) {
    error(403);
}
echo $Twig->render('admin/forum-navigation.twig', [
    'auth' => $Viewer->auth(),
    'list' => Users::get_nav_items(),
]);
