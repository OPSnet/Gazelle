<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

echo $Twig->render('admin/user-custom-permission.twig', [
    'list' => (new Gazelle\Manager\User)->findAllByCustomPermission()
]);
