<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$DB->prepared_query("
    SELECT ID, Name FROM staff_groups ORDER BY Sort
");

echo $Twig->render('admin/privilege-edit.twig', [
    'auth'       => $Viewer->auth(),
    'js'         => (new Gazelle\Util\Validator)->generateJS('permissionsform'),
    'group_list' => $DB->to_array(false, MYSQLI_ASSOC, false),
    'privilege'  => isset($privilege) ? $privilege : [],
]);
