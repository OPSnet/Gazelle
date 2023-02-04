<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$artist = (new Gazelle\Manager\Artist)->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    $id = display_str($_GET['artistid']);
    error("Cannot find an artist with the ID {$id}: See the <a href=\"log.php?search=Artist+$id\">site log</a>.");
}
$artistId = $artist->id();

// Get the artist name and the body of the last revision
[$name, $image, $body, $showCase, $discogsId] = $artist->editableInformation();
if (!$name) {
    error("Cannot find an artist with the ID {$artistId}: See the <a href=\"log.php?search=Artist+$artistId\">site log</a>.");
}

echo $Twig->render('artist/edit.twig', [
    'alias_info'         => $artist->aliasInfo(),
    'auth'               => $Viewer->auth(),
    'body'               => $body,
    'discogs_id'         => $discogsId,
    'id'                 => $artistId,
    'image'              => $image,
    'is_editor'          => $Viewer->permitted('torrents_edit'),
    'is_showcase_editor' => $Viewer->permitted('artist_edit_vanityhouse'),
    'is_mod'             => $Viewer->permitted('users_mod'),
    'locked'             => $artist->isLocked(),
    'name'               => $name,
    'showcase'           => $showCase,
]);
