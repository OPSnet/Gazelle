<?php

if (!$Viewer->permitted("admin_global_notification")) {
    error(404);
}

echo $Twig->render('admin/mass-pm.twig', [
    'auth'  => $Viewer->auth(),
    'body'  => new Gazelle\Util\Textarea('body', '', 95, 10),
    'class' => (new Gazelle\Manager\User)->classList(),
]);
