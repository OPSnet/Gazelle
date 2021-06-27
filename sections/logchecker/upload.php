<?php

use OrpheusNET\Logchecker\Logchecker;

View::show_header('Logchecker', 'upload');
echo $Twig->render('logchecker/upload.twig', [
    'accepted' => Logchecker::getAcceptValues(),
    'list'     => (new Gazelle\Manager\Torrent)->missingLogfiles($Viewer->id()),
]);
View::show_footer();
