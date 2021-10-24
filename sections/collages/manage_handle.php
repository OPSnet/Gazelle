<?php

authorize();

$collageID = (int)$_POST['collageid'];
if (!$collageID) {
    error(404);
}
$collage = new Gazelle\Collage($collageID);
if ($collage->isPersonal() && !$collage->isOwner($Viewer->id()) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

$groupId = (int)$_POST['groupid'];
if (!$groupId) {
    error(404);
}

if (isset($_POST['submit']) && $_POST['submit'] === 'Remove') {
    $collage->removeEntry($groupId);
} elseif (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
} else {
    $sequence = $_POST['sort'];
    if (!is_number($sequence)) {
        error(404);
    }
    $collage->updateSequenceEntry($groupId, $sequence);
}
$collage->flush();

header("Location: collages.php?action=manage&collageid=$collageID");
