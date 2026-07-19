<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();
if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}

$role = (int)$_GET['importance'];
if (!$role) {
    Error400::error('No role specified to delete');
}
$tgMan = new Manager\TGroup();
$tgroup = $tgMan->findById((int)$_GET['groupid']);
if (is_null($tgroup)) {
    Error404::error();
}
$artist = new Manager\Artist()->findByAliasId((int)$_GET['aliasid']);
if (is_null($artist)) {
    Error404::error();
}

// save data in case removeArtist() deletes the artist
$artistId = $artist->id;
$artistName = $artist->name();

if ($tgroup->removeArtist($artist, $role)) {
    $tgroup->refresh();
    $label = "$artistId ($artistName) [" . ARTIST_TYPE[$role] . "]";
    $tgroup->logger()->group($tgroup, $Viewer, "removed artist $label")
        ->general(
            "Artist $label removed from group {$tgroup->label()} by user {$Viewer->label()}"
        );
}

header('Location: ' . redirectUrl($tgroup->location()));
