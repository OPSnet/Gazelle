<?php

if (!$Viewer->permitted('users_mod') && !$Viewer->isLocked()) {
    header('Location: /');
    exit;
}

echo $Twig->render('user/locked.twig', [
    'viewer' => $Viewer,
]);
