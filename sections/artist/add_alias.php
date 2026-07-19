<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}
authorize();

$redirectId = (int)$_POST['redirect'];
$newName = Artist::sanitize($_POST['name']);
if (empty($newName)) {
    Error400::error('The specified name is empty.');
}

$artMan = new Manager\Artist();
$artist = $artMan->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    Error404::error();
} elseif ($artist->isLocked() && !$Viewer->permitted('users_mod')) {
    Error400::error('This artist is locked.');
}

$otherArtist = $artMan->findByName($newName);
if ($otherArtist) {
    if ($otherArtist->id === $artist->id) {
        Error400::error("This artist already has the specified alias.");
    }
    echo $Twig->render('artist/error-alias.twig', [
        'alias'  => $newName,
        'artist' => $otherArtist,
    ]);
    exit;
}

$redirArtist = null;
if ($redirectId) {
    $redirArtist = $artMan->findByAliasId($redirectId);
    if (is_null($redirArtist)) {
        Error400::error("No alias found for desired redirect.");
    }
    if ($artist->id !== $redirArtist->id) {
        Error400::error("Cannot redirect to the alias of a different artist.");
    }
}

$artist->addAlias($newName, $redirectId, $Viewer);

header("Location:" . redirectUrl("artist.php?action=edit&artistid={$artist->id}"));
