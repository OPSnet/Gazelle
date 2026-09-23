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

$artMan = new Manager\Artist();
$artist = $artMan->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    Error400::error('Please select a valid artist to change.');
} elseif ($artist->isLocked() && !$Viewer->permitted('users_mod')) {
    Error400::error('This artist is locked.');
}

$new = $artMan->findById((int)($_POST['newartistid'] ?? 0));
if (is_null($new)) {
    $new = $artMan->findByName($_POST['newartistname'] ?? '');
    if (is_null($new)) {
        Error404::error('Please enter a valid artist ID number or a valid artist name.');
    }
}

if ($artist->id == $new->id) {
    Error400::error('You cannot merge an artist with itself.');
}

$redirect = (bool)$_POST['redirect'];

if (isset($_POST['confirm'])) {
    $new->merge($artist, $redirect, $Viewer);
    header("Location: artist.php?action=edit&artistid={$new->id}");
    return;
}

echo $Twig->render('artist/merge.twig', [
    'artist'   => $artist,
    'new'      => $new,
    'redirect' => $redirect,
    'viewer'   => $Viewer,
]);
