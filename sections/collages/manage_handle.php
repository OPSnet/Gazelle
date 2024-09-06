<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_collages_manage')) {
    error(403);
}

authorize();

$tgroup = (new Gazelle\Manager\TGroup())->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}
$collage = (new Gazelle\Manager\Collage())->findById((int)($_POST['collageid']));
if (is_null($collage)) {
    error(404);
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

if (($_POST['submit'] ?? '') === 'Remove') {
    $userId = $collage->entryUserId($tgroup);
    if ($collage->removeEntry($tgroup)) {
        (new Gazelle\Log())->general(sprintf("Collage %d (%s) group entry {$tgroup->id()} (added by user $userId) removed by %s",
            $collage->id(), $collage->name(), $Viewer->username()
        ));
    }
} elseif (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
} else {
    $sequence = (int)$_POST['sort'];
    if (!$sequence) {
        error(404);
    }
    $collage->updateSequenceEntry($tgroup, $sequence);
}
$collage->flush();

header("Location: {$collage->location()}&action=manage");
