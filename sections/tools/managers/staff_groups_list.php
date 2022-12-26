<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

echo $Twig->render('admin/staff-group.twig', [
    'auth' => $Viewer->auth(),
    'list' => (new Gazelle\Manager\StaffGroup)->groupList(),
]);
