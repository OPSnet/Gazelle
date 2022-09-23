<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

echo $Twig->render('admin/irc-list.twig', [
    'list'   => (new Gazelle\Manager\IRC)->list(),
    'viewer' => $Viewer,
]);
