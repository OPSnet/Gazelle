<?php

$artistMan = new Gazelle\Manager\Artist;
$artist = $artistMan->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    error(404);
}

echo $Twig->render('revision.twig', [
    'id'   => $artist->id(),
    'list' => $artist->revisionList(),
    'name' => $artist->name(),
    'url'  => "artist.php?id=",
]);
