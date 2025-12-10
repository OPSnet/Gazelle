<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

/* lazy-load the torrent file list */
header('Content-Type: application/json');

echo json_encode(
    $Twig->render('torrent/detail-filelist.twig', [
        'torrent' => new Manager\Torrent()
            ->findById((int)($_GET['id'] ?? 0)),
        'viewer' => $Viewer,
    ])
);
