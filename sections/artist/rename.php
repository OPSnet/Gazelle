<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

authorize();

$artistMan = new Gazelle\Manager\Artist();
$artist = $artistMan->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    error(404);
}
$oldName = $artist->name();
if (!$artist->getAlias($oldName)) {
    error('Could not find existing alias ID');
}
$newName = Gazelle\Artist::sanitize($_POST['name']);
if (empty($newName)) {
    error('No new name given.');
}
if ($oldName == $newName) {
    error('The new name is identical to <a href="artist.php?artistname=' . display_str($oldName) . '">the old name</a>."');
}

$new = $artist->smartRename(
    $newName,
    $artistMan,
    new Gazelle\Manager\Comment(),
    new Gazelle\Manager\Request(),
    new Gazelle\Manager\TGroup(),
    $Viewer,
);

header("Location: {$new->location()}");
