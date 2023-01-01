<?php

$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    error(404);
}

if (!$Viewer->permitted('users_mod')) {
    if (time() - strtotime($torrent->lastReseedRequest()) < 864000) {
        error('There was already a re-seed request for this torrent within the past 10 days.');
    }
    if (time() - strtotime($torrent->lastActiveDate()) < 345678) {
        error(403);
    }
}

echo $Twig->render('torrent/reseed-result.twig', [
    'torrent' => $torrent,
    'total'   => $torrent->issueReseedRequest($Viewer),
]);
