<?php

if (!$Viewer->permitted('site_admin_requests')) {
    error(403);
}

$request = (new Gazelle\Manager\Request)->findById((int)$_GET['id']);
if (is_null($request)) {
    error(404);
}

View::show_header('Edit request bounty');
echo $Twig->render('request/edit-bounty.twig', [
    'auth'    => $Viewer->auth(),
    'request' => $request,
]);
View::show_footer();
