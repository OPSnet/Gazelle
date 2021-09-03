<?php

authorize();

if (!$Viewer->permitted('site_delete_tag')) {
    error(403);
}
$similarId = (int)$_GET['similarid'];
if (!$similarId) {
    error(0);
}
$artist = (new Gazelle\Manager\Artist)->findById((int)($_GET['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}

$artist->removeSimilar($similarId);
header("Location: " . redirectUrl("artist.php?id=" . $artist->id()));
