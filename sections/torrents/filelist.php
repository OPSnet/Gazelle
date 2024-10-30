<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

/* lazy-load the torrent file list */
header('Content-Type: application/json; charset=utf-8');

echo json_encode(
    $Twig->render('torrent/detail-filelist.twig', [
        'torrent' => (new Gazelle\Manager\Torrent())
            ->findById((int)($_GET['id'] ?? 0)),
        'viewer' => $Viewer,
    ])
);
