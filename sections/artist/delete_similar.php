<?php

authorize();

if (!$Viewer->permitted('site_delete_tag')) {
    error(403);
}
$similarId = (int)($_GET['similarid'] ?? 0);
if (!$similarId) {
    error(0);
}
$artMan = new Gazelle\Manager\Artist;
$artist = $artMan->findById((int)($_GET['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}

$artist->removeSimilar($similarId, $artMan, new Gazelle\Manager\Request);
header("Location: " . redirectUrl($artist->location()));
