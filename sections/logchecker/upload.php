<?php

use OrpheusNET\Logchecker\Logchecker;

echo $Twig->render('logchecker/upload.twig', [
    'accepted' => Logchecker::getAcceptValues(),
    'list'     => (new Gazelle\Manager\Torrent())->setViewer($Viewer)->missingLogfiles($Viewer->id()),
]);
