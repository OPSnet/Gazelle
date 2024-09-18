<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

$userMan = new Gazelle\Manager\User();
echo $Twig->render('admin/stats/torrent.twig', [
    'notification' => new Gazelle\Manager\Notification(),
    'reaper'       => new Gazelle\Torrent\Reaper(new Gazelle\Manager\Torrent(), $userMan),
    'torr_stat'    => new Gazelle\Stats\Torrent(),
    'user_stat'    => new Gazelle\Stats\Users(),
    'user_man'     => $userMan,
]);
