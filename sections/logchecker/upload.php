<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

use OrpheusNET\Logchecker\Logchecker;

echo $Twig->render('logchecker/upload.twig', [
    'accepted' => Logchecker::getAcceptValues(),
    'list'     => (new Gazelle\Manager\Torrent())->setViewer($Viewer)->missingLogfiles($Viewer->id()),
]);
