<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$DB->prepared_query("
    SELECT ID AS id,
        Sort AS sequence,
        Name AS name
    FROM staff_groups
    ORDER BY Sort
");
echo $Twig->render('admin/staff-group.twig', [
    'auth' => $Viewer->auth(),
    'list' => $DB->to_array(false, MYSQLI_ASSOC, false),
]);
