<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

$redirectId = (int)$_POST['redirect'];
$newName = Gazelle\Artist::sanitize($_POST['name']);
if (empty($newName)) {
    error('The specified name is empty.');
}

$artMan = new Gazelle\Manager\Artist();
$artist = $artMan->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    error(404);
} elseif ($artist->isLocked() && !$Viewer->permitted('users_mod')) {
    error('This artist is locked.');
}

$otherArtist = $artMan->findByName($newName);
if ($otherArtist) {
    if ($otherArtist->id() === $artist->id()) {
        error("This artist already has the specified alias.");
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
        error("No alias found for desired redirect.");
    }
    if ($artist->id() !== $redirArtist->id()) {
        error("Cannot redirect to the alias of a different artist.");
    }
}

$artist->addAlias($newName, $redirectId, $Viewer);

header("Location:" . redirectUrl("artist.php?action=edit&artistid={$artist->id()}"));
