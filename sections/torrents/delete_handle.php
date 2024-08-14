<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

authorize();

$torrent = (new Gazelle\Manager\Torrent())->setViewer($Viewer)->findById((int)$_POST['torrentid']);
if (is_null($torrent)) {
    error(404);
}
$torrentId = $torrent->id();
$uploader  = $torrent->uploader();

if ($Viewer->id() != $uploader->id() && !$Viewer->permitted('torrents_delete')) {
    error(403);
}
if ($Viewer->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX && !$Viewer->permitted('torrents_delete_fast')) {
    error('You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. Please contact a staff member if you need to delete more.');
}
if ($torrent->hasUploadLock()) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

$fullName = $torrent->fullName();
$path     = $torrent->path();
$infohash = $torrent->infohash();
$size     = $torrent->size();
$reason   = implode(' ', array_map('trim', [$_POST['reason'], $_POST['extra']]));

[$success, $message] = $torrent->remove($Viewer, $reason);
if (!$success) {
    error($message);
}

(new Gazelle\Manager\User())->sendRemovalPm(
    $uploader, $torrentId, $fullName, $path,
    "Torrent $torrentId $fullName (" . number_format($size / (1024 * 1024), 2) . ' MiB '
        . strtoupper($infohash) . ") was deleted by " . $Viewer->username() . ": $reason",
    0,
    $Viewer->id() != $uploader->id()
);

echo $Twig->render('torrent/deleted.twig', [
    'name' => $fullName,
]);
