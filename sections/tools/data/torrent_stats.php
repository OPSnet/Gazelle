<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_view_flow')) {
    Error403::error();
}

$userMan = new Manager\User();
echo $Twig->render('admin/stats/torrent.twig', [
    'notification' => new Manager\Notification(),
    'reaper'       => new Torrent\Reaper(new Manager\Torrent(), $userMan),
    'torr_stat'    => new Stats\Torrent(),
    'user_stat'    => new Stats\Users(),
    'user_man'     => $userMan,
]);
