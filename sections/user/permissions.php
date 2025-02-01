<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$userMan = new Gazelle\Manager\User();
$user = $userMan->findById((int)($_REQUEST['userid']));
if (is_null($user)) {
    error(404);
}

if (isset($_POST['action'])) {
    authorize();
    $privilegeList = [];
    foreach (array_map('strval', array_keys($_POST)) as $key) {
        if (str_starts_with($key, 'perm_')) {
            $privilegeList[substr($key, 5)] = true;
        }
    }
    $user->modifyPrivilegeList($privilegeList);
}

echo $Twig->render('user/privilege-list.twig', [
    'user'   => $user,
    'viewer' => $Viewer,
]);
