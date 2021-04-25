<?php

use OrpheusNET\Logchecker\Logchecker;

View::show_header('Logchecker', 'upload');
echo $Twig->render('logchecker/upload.twig', [
    'accepted' => Logchecker::getAcceptValues(),
    'list'     => (new Gazelle\Manager\Torrent)->missingLogfiles($LoggedUser['ID']),
]);
View::show_footer();
