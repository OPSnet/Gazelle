<?php

authorize();

if (!$Viewer->permitted('site_collages_create')) {
    error(403);
}
$artistId = (int)$_POST['artistid'];
if (!$artistId) {
    error(404);
}
$collage = (new Gazelle\Manager\Collage())->findById((int)$_POST['collageid']);
if (is_null($collage)) {
    error(404);
}
if (!$collage->isArtist()) {
    error(403);
}

if ($_POST['submit'] === 'Remove') {
    $collage->removeEntry($artistId);
} elseif (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
} else {
    $sequence = (int)$_POST['sort'];
    if (!$sequence) {
        error(404);
    }
    $collage->updateSequenceEntry($artistId, $sequence);
}
$collage->flush();

header("Location: collages.php?action=manage_artists&collageid=" . $collage->id());
