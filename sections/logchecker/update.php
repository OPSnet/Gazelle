<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

echo $Twig->render('riplog/update.twig', [
    'accepted' => \OrpheusNET\Logchecker\Logchecker::getAcceptValues(),
    'list'     => new Manager\Torrent()->logFileList($Viewer->id()),
]);
