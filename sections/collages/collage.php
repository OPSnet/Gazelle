<?php

$collageMan = new Gazelle\Manager\Collage();
$Collage = $collageMan->findById((int)($_GET['id'] ?? 0));
if (is_null($Collage)) {
    error(404);
}

if ($Collage->isDeleted()) {
    header("Location: log.php?search=Collage+" . $Collage->id());
    exit;
}

require_once($Collage->isArtist() ? 'collage_artists.php' : 'collage_torrent.php');

View::show_footer();
