<?php

if (!check_perms('admin_manage_permissions')) {
    error(403);
}

View::show_header('Special Users List');
echo $Twig->render('admin/user-custom-permission.twig', [
    'list' => (new Gazelle\Manager\User)->findAllByCustomPermission()
]);
View::show_footer();
