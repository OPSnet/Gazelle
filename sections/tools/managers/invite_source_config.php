<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_manage_invite_source')) {
    error(403);
}

$manager = new Gazelle\Manager\InviteSource();
if (!empty($_POST['name'])) {
    authorize();
    $manager->create(trim($_POST['name']));
}
$remove = array_key_extract_suffix('remove-', $_POST);
if ($remove) {
    authorize();
    foreach ($remove as $r) {
        $manager->remove($r);
    }
}

echo $Twig->render('admin/invite-source-config.twig', [
    'auth' => $Viewer->auth(),
    'list' => $manager->usageList(),
]);
