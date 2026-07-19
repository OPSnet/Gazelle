<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

echo $Twig->render('admin/torrent-report-view.twig', [
    'list'   => new Manager\Torrent\ReportType()->list(),
    'viewer' => $Viewer,
]);
