<?php
authorize();

$torrent = (new Gazelle\Manager\Torrent)->setViewer($Viewer)->findById((int)$_POST['torrentid']);
if (is_null($torrent)) {
    error(404);
}
$torrentId  = $torrent->id();
$uploaderId = $torrent->uploaderId();

if ($Viewer->id() != $uploaderId && !$Viewer->permitted('torrents_delete')) {
    error(403);
}
if ($Viewer->torrentRecentRemoveCount(USER_TORRENT_DELETE_HOURS) >= USER_TORRENT_DELETE_MAX && !$Viewer->permitted('torrents_delete_fast')) {
    error('You have recently deleted ' . USER_TORRENT_DELETE_MAX
        . ' torrents. Please contact a staff member if you need to delete more.');
}
if ($Cache->get_value("torrent_{$torrentId}_lock")) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

$fullName = $torrent->fullName();
$infohash = $torrent->infohash();
$size     = $torrent->size();
$reason   = implode(' ', array_map('trim', [$_POST['reason'], $_POST['extra']]));

[$success, $message] = $torrent->remove($Viewer->id(), $reason);
if (!$success) {
    error($message);
}

(new Gazelle\Manager\User)->sendRemovalPM(
    $torrentId, $uploaderId, $fullName,
    "Torrent $torrentId $fullName (" . number_format($size / (1024 * 1024), 2) . ' MiB '
        . strtoupper($infohash) . ") was deleted by " . $Viewer->username() . ": $reason",
    0,
    $Viewer->id() != $uploaderId
);

echo $Twig->render('template/torrent/deleted.twig', [
    'name' => $fullName,
]);
