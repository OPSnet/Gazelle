<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

authorize();

$torrent = new Manager\Torrent()->findById((int)$_POST['torrentid']);
if (is_null($torrent)) {
    Error404::error();
}
$torrentId = $torrent->id;
$uploader  = $torrent->uploader();

if ($Viewer->id != $uploader->id && !$Viewer->permitted('torrents_delete')) {
    Error403::error();
}
if (
    $Viewer->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX
    && !$Viewer->permitted('torrents_delete_fast')
) {
    Error400::error(
        'You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. If you think there is a problem with this torrent, please report it with [RP] instead.'
    );
}
if ($torrent->hasUploadLock()) {
    Error400::error(
        'Torrent cannot be deleted because the upload process is not completed yet. Please try again later.'
    );
}

$fullName = $torrent->fullName();
$path     = $torrent->path();
$infohash = $torrent->infohash();
$size     = $torrent->size();
$reason   = implode(' ', array_map(trim(...), [$_POST['reason'], $_POST['extra']]));

[$success, $message] = $torrent->removeTorrent($Viewer, $reason);
if (!$success) {
    Error400::error($message);
}

new Manager\User()->sendRemovalPm(
    $uploader, $torrentId, $fullName, $path,
    "Torrent $torrentId $fullName (" . number_format($size / (1024 * 1024), 2) . ' MiB '
        . strtoupper($infohash) . ") was deleted by " . $Viewer->username() . ": $reason",
    0,
    $Viewer->id != $uploader->id
);

echo $Twig->render('torrent/deleted.twig', [
    'name' => $fullName,
]);
