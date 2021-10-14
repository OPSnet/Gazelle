<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}
echo $Twig->render('admin/forum-category.twig', [
    'auth' => $Viewer->auth(),
    'list' => (new Gazelle\Manager\Forum)->categoryUsageList(),
]);
