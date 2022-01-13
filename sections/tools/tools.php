<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

echo $Twig->render('admin/toolbox.twig', ['viewer' => $Viewer]);
