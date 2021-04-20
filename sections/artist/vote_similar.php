<?php

$Way = trim($_GET['way']);
if (!in_array($Way, ['up', 'down'])) {
    error(0);
}
$artistMan = new Gazelle\Manager\Artist;
$artist = $artistMan->findById((int)($_GET['artistid'] ?? 0), 0);
$similarId = (int)($_GET['similarid'] ?? 0);
if (is_null($artist) || !$similarId) {
    error(404);
}
$artist->voteSimilar($LoggedUser['ID'], $similarId, $Way === 'up');

header("Location: " . redirectUrl("artist.php?id=" . $artist->id()));
