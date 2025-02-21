<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_collages_create')) {
    error(403);
}

authorize();

$artist = (new Gazelle\Manager\Artist())->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}
$collage = (new Gazelle\Manager\Collage())->findById((int)$_POST['collageid']);
if (is_null($collage)) {
    error(404);
}
if (!$collage->isArtist()) {
    error(403);
}

if (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
} elseif ($_POST['submit'] === 'Remove') {
    $collage->removeEntry($artist);
} else {
    $sequence = (int)$_POST['sort'];
    if (!$sequence) {
        error(404);
    }
    $collage->updateSequenceEntry($artist, $sequence);
}
$collage->flush();

header("Location: collages.php?action=manage_artists&collageid={$collage->id()}");
