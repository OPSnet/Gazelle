<?php

if (!$Viewer->permitted('admin_manage_invite_source')) {
    error(403);
}

$manager = new Gazelle\Manager\InviteSource;
if (!empty($_POST['name'])) {
    authorize();
    $manager->create(trim($_POST['name']));
}
$remove = array_keys(array_filter($_POST, function ($x) { return preg_match('/^remove-\d+$/', $x);}, ARRAY_FILTER_USE_KEY));
if ($remove) {
    authorize();
    foreach ($remove as $r) {
        $manager->remove((int)explode('-', $r)[1]);
    }
}

echo $Twig->render('admin/invite-source-config.twig', [
    'auth' => $Viewer->auth(),
    'list' => $manager->listByUse(),
]);
