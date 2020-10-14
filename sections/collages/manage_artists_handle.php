<?php

authorize();

$CollageID = (int)$_POST['collageid'];
if (!$CollageID) {
    error(404);
}
$collage = new Gazelle\Collage($CollageID);

if (!$collage->isArtist()) {
    error(403);
}
if (!check_perms('site_collages_create')) {
    error(403);
}

$ArtistID = (int)$_POST['artistid'];
if (!$ArtistID) {
    error(404);
}

if ($_POST['submit'] === 'Remove') {
    $collage->removeArtist($ArtistID);

} elseif (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
} else {
    $sequence = $_POST['sort'];
    if (!is_number($sequence)) {
        error(404);
    }
    $collage->updateSequenceEntry($ArtistID, $sequence);
}
$collage->flush();

header("Location: collages.php?action=manage_artists&collageid=$CollageID");
