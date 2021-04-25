<?php

$artistMan = new Gazelle\Manager\Artist;
$artist = $artistMan->findById((int)$_GET['artistid'], 0);
if (is_null($artist)) {
    error(404);
}

View::show_header($artist->name() . " &rsaquo; Revision History");
echo $Twig->render('revision.twig', [
    'id'   => $artist->id(),
    'list' => $artist->revisionList(),
    'name' => $artist->name(),
    'url'  => "artist.php?id=",
]);
View::show_footer();
