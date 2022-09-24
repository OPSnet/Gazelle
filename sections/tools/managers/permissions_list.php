<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

if (isset($_REQUEST['id']) && $_REQUEST['id'] === 'new') {
    require_once('permissions_edit.php');
    exit;
}

echo $Twig->render('admin/privilege-usage.twig', [
    'list' => (new Gazelle\Manager\Privilege)->usageList(),
    'viewer' => $Viewer,
]);
