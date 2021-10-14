<?php

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}
echo $Twig->render('admin/user-info.twig', [
    'now'  => Date('Y-m-d H:i:s'),
    'user' => $user,
]);
