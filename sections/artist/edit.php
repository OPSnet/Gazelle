<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$artist = (new Gazelle\Manager\Artist)->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    $id = display_str($_GET['artistid']);
    error("Cannot find an artist with the ID {$id}: See the <a href=\"log.php?search=Artist+$id\">site log</a>.");
}

echo $Twig->render('artist/edit.twig', [
    'alias_info'         => $artist->aliasInfo(),
    'auth'               => $Viewer->auth(),
    'body'               => $artist->body(),
    'discogs_id'         => $artist->discogsId(),
    'id'                 => $artist->id(),
    'image'              => $artist->image(),
    'is_editor'          => $Viewer->permitted('torrents_edit'),
    'is_showcase_editor' => $Viewer->permitted('artist_edit_vanityhouse'),
    'is_mod'             => $Viewer->permitted('users_mod'),
    'locked'             => $artist->isLocked(),
    'name'               => $artist->name(),
    'showcase'           => $artist->isShowcase(),
]);
