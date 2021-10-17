<?php

if (!$Viewer->permitted('site_torrents_notify')) {
    error(403);
}
authorize();

$artist = (new Gazelle\Manager\Artist)->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    error(404);
}
$Viewer->addArtistNotification($artist);

header("Location: " . redirectUrl("artist.php?id=" . $artist->id()));
