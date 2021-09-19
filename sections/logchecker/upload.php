<?php

use OrpheusNET\Logchecker\Logchecker;

echo $Twig->render('logchecker/upload.twig', [
    'accepted' => Logchecker::getAcceptValues(),
    'list'     => (new Gazelle\Manager\Torrent)->missingLogfiles($Viewer->id()),
]);
