<?php

$way = trim($_GET['way']);
if (!in_array($way, ['up', 'down'])) {
    error(0);
}

$artistMan = new Gazelle\Manager\Artist();
$artist    = $artistMan->findById((int)($_GET['artistid'] ?? 0));
$similar   = $artistMan->findById((int)($_GET['similarid'] ?? 0));
if (is_null($artist) || is_null($similar)) {
    error("Cannot vote on two artists that are dissimilar");
}
$artist->similar()->voteSimilar($Viewer, $similar, $way === 'up');

header("Location: " . redirectUrl($artist->location()));
