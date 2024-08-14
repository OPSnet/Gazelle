<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

echo $Twig->render('logchecker/update.twig', [
    'accepted' => OrpheusNET\Logchecker\Logchecker::getAcceptValues(),
    'list'     => (new Gazelle\Manager\Torrent())->logFileList($Viewer->id()),
]);
