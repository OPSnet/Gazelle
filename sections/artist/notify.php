<?php
authorize();
if (!check_perms('site_torrents_notify')) {
    error(403);
}

try {
    $artist = new Gazelle\Artist((int)$_GET['artistid']);
} catch (Gazelle\Exception\ResourceNotFoundException $e) {
    error(404);
}
$user = new Gazelle\User($LoggedUser['ID']);
$user->addArtistNotification($artist);

header("Location: " . redirectUrl("artist.php?id=" . $artist->id()));
