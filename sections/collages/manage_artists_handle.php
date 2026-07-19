<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_collages_create')) {
    Error403::error();
}

authorize();

$collage = new Manager\Collage()->findById((int)$_POST['collageid']);
if (is_null($collage)) {
    Error404::error("Did not identify a collage");
}
if (!$collage->isArtist()) {
    Error400::error("This is not an Artist collage");
}
if (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
    header("Location: collages.php?id={$collage->id}");
    exit;
}

$artist = new Manager\Artist()->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    Error404::error("Did not identify an artist");
}
if ($_POST['submit'] === 'Remove') {
    $collage->removeEntry($artist);
} else {
    $collage->updateSequenceEntry($artist, (int)$_POST['sort']);
}
$collage->flush();

header("Location: collages.php?action=manage_artists&collageid={$collage->id}");
