<?php

$collageMan = new Gazelle\Manager\Collage;
$Collage = $collageMan->findById((int)($_GET['id'] ?? 0));
if (is_null($Collage)) {
    error(404);
}

if ($Collage->isDeleted()) {
    header("Location: log.php?search=Collage+" . $Collage->id());
    exit;
}

$Collage->setViewer($Viewer);
$CollageID = $Collage->id();
$CollageCovers = ($Viewer->option('CollageCovers') ?: 25) * (1 - (int)$Viewer->option('HideCollage'));
$CollagePages = [];
$NumGroups = $Collage->numEntries();
require_once($Collage->isArtist() ? 'artist_collage.php' : 'torrent_collage.php');

View::show_footer();
