<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_torrents_notify')) {
    Error403::error();
}
authorize();

$artist = new Manager\Artist()->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    Error404::error();
}
$Viewer->addArtistNotification($artist);

header("Location: " . redirectUrl("artist.php?id=" . $artist->id));
