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
$userId = $user->id();

if (isset($_POST['action'])) {
    authorize();
    $user->modifyPrivilegeList(
        array_filter($_POST, fn(string $p): bool => str_starts_with($p, 'perm_'), ARRAY_FILTER_USE_KEY)
    );
}

echo $Twig->render('user/privilege-list.twig', [
    'auth' => $Viewer->auth(),
    'user' => $user,
]);
