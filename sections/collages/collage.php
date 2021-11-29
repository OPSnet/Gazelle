<?php
ini_set('max_execution_time', 600);

$CollageID = (int)$_GET['id'];
if (!$CollageID) {
    error(404);
}
$Collage = new Gazelle\Collage($CollageID);

// TODO: ensure collage subscription is updated for viewer

if ($Collage->isDeleted()) {
    header("Location: log.php?search=Collage+$CollageID");
    exit;
}

$Collage->setViewer($Viewer);
$NumGroups = $Collage->numEntries();
$CollageCovers = ($Viewer->option('CollageCovers') ?? 25) * !(int)($Viewer->option('HideCollage'));
$CollagePages = [];
require_once($Collage->isArtist() ? 'artist_collage.php' : 'torrent_collage.php');

View::show_footer();
