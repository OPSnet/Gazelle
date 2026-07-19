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

echo $Twig->render('admin/economy.twig', [
    'eco'     => new Stats\Economic(),
    'torrent' => new Stats\Torrent(),
]);
