<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$privMan = new Gazelle\Manager\Privilege;
$groupList = $privMan->staffGroupList();

if (isset($_REQUEST['id'])) {
    if ($_REQUEST['id'] === 'new') {
        $privilege = null;
    } else {
        $privilege = $privMan->findById((int)$_REQUEST['id']);
        if (is_null($privilege)) {
            header("Location: tools.php?action=permissions");
            exit;
        }
    }
}

echo $Twig->render('admin/privilege-edit.twig', [
    'js'         => (new Gazelle\Util\Validator)->generateJS('permissionsform'),
    'group_list' => $groupList,
    'privilege'  => $privilege,
    'viewer'     => $Viewer,
]);
