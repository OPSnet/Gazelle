<?php

authorize();
if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$role = (int)$_GET['importance'];
if (!$role) {
    error(0);
}
$tgMan = new Gazelle\Manager\TGroup;
$tgroup = $tgMan->findById((int)$_GET['groupid']);
if (is_null($tgroup)) {
    error(404);
}
$artist = (new Gazelle\Manager\Artist)->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    error(404);
}
$artistId = $artist->id();
$artistName = $artist->name();

if ($tgroup->setViewer($Viewer)->removeArtist($artist, $role)) {
    $tgMan->refresh($tgroup->id());
}

$label = "$artistId ($artistName) [" . ARTIST_TYPE[$role] . "]";
(new Gazelle\Log)->group($tgroup->id(), $Viewer->id(), "removed artist $label")
    ->general("Artist $label removed from group " . $tgroup->label() . " by user " . $Viewer->label());

header('Location: ' . redirectUrl($tgroup->url()));
