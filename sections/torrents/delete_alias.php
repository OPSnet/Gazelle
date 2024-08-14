<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();
if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$role = (int)$_GET['importance'];
if (!$role) {
    error(0);
}
$tgMan = new Gazelle\Manager\TGroup();
$tgroup = $tgMan->findById((int)$_GET['groupid']);
if (is_null($tgroup)) {
    error(404);
}
$artist = (new Gazelle\Manager\Artist())->findByAliasId((int)$_GET['aliasid']);
if (is_null($artist)) {
    error(404);
}

// save data in case removeArtist() deletes the artist
$artistId = $artist->id();
$artistName = $artist->name();

$logger = new Gazelle\Log();
if ($tgroup->removeArtist($artist, $role, $Viewer, $logger)) {
    $tgroup->refresh();
    $label = "$artistId ($artistName) [" . ARTIST_TYPE[$role] . "]";
    $logger->group($tgroup, $Viewer, "removed artist $label")
        ->general("Artist $label removed from group " . $tgroup->label() . " by user " . $Viewer->label());
}

header('Location: ' . redirectUrl($tgroup->location()));
