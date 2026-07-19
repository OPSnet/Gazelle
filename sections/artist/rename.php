<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}

authorize();

$artistMan = new Manager\Artist();
$artist = $artistMan->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist) || empty($_POST['aliasid'])) {
    Error404::error();
} elseif ($artist->isLocked() && !$Viewer->permitted('users_mod')) {
    Error400::error('This artist is locked.');
}

$aliasId = (int)$_POST['aliasid'];
$newName = Artist::sanitize($_POST['name']);
if (empty($newName)) {
    Error400::error('No new name given.');
} elseif (!isset($artist->aliasList()[$aliasId])) {
    Error400::error('Could not find existing alias ID');
} elseif ($artist->aliasList()[$aliasId]['name'] === $newName) {
    Error400::error('The new name is identical to the old name."');
}
$oldName = $artist->aliasList()[$aliasId]['name'];

$otherArtist = $artistMan->findByName($newName);
if (!is_null($otherArtist) && $otherArtist->id !== $artist->id) {
    Error400::error(
        "An artist with this alias already exists: {$otherArtist->name()} ({$otherArtist->id})"
    );
}

$result = $artist->renameAlias($aliasId, $newName, $Viewer);
if (is_null($result)) {
    Error400::error("The specified name is already in use.");
}

header("Location: artist.php?artistid={$artist->id}&action=edit");
