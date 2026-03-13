<?php

declare(strict_types=1);

namespace Gazelle;

$collageMan = new Manager\Collage();
$collage = $collageMan->findById((int)($_GET['id'] ?? 0));
if (is_null($collage)) {
    Error404::error();
}
if ($collage->isDeleted()) {
    header("Location: log.php?search=Collage+" . $collage->id);
    exit;
}

require __DIR__ . ($collage->isArtist() ? '/collage_artists.php' : '/collage_torrent.php');
