<?php

$torMan = new Gazelle\Manager\Torrent;
$torrent = $torMan->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    $torrent = $torMan->findDeletedById((int)$_GET['torrentid']);
}
if (is_null($torrent)) {
    error(404);
}

echo $Twig->render('torrent/riplog.twig', [
    'id'        => $torrent->id(),
    'list'      => $torrent->logfileList(new Gazelle\File\RipLog, new Gazelle\File\RipLogHTML),
    'log_score' => $torrent->logScore(),
    'viewer'    => $Viewer,
]);
