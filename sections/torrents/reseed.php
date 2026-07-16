<?php

// @phpstan-var \Gazelle\User $Viewer
// @phpstan-var \Twig\Environment $Twig

declare(strict_types=1);

namespace Gazelle;

$torrent = new Manager\Torrent()->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    Error404::error();
}

if (!$Viewer->permitted('users_mod')) {
    match (true) {
        is_null($torrent->lastActiveDate()) && !is_null($torrent->lastReseedRequestDate())
            => Error400::error(
                'There was already a re-seed request for this torrent within the past '
                    . RESEED_NEVER_ACTIVE_TORRENT . ' days.'
                ),
        !is_null($torrent->lastReseedRequestDate())
            => Error400::error(
                'There was already a re-seed request for this torrent within the past '
                    . RESEED_TORRENT . ' days.'
                ),
        default => false,
    };
}

echo $Twig->render('torrent/reseed-result.twig', [
    'torrent' => $torrent,
    'total'   => $torrent->issueReseedRequest($Viewer),
]);
