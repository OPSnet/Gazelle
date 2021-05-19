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
}
else {
    $Collage->setViewerId($LoggedUser['ID']);
    $NumGroups = $Collage->numEntries();
    $CollageCovers = isset($LoggedUser['CollageCovers']) ? $LoggedUser['CollageCovers'] : 25 * abs(($LoggedUser['HideCollage'] ?? 0) - 1);
    $CollagePages = [];
    $bookmark = new Gazelle\Bookmark;
    require_once($Collage->isArtist() ? 'artist_collage.php' : 'torrent_collage.php');
}

View::show_footer();
