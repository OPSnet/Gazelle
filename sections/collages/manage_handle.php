<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_collages_manage')) {
    error(403);
}

authorize();

$collage = (new Gazelle\Manager\Collage())->findById((int)($_POST['collageid']));
if (is_null($collage)) {
    error("Cannot find the requested collage");
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

if (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
} elseif (isset($_POST['groupid'])) {
    $tgroup = (new Gazelle\Manager\TGroup())->findById((int)($_POST['groupid'] ?? 0));
    if (is_null($tgroup)) {
        error("Cannot find torrent group");
    }
    if (isset($_POST['sort'])) {
        $collage->updateSequenceEntry($tgroup, (int)$_POST['sort']);
    } elseif ($_POST['submit'] === 'Remove') {
        $userId = $collage->entryUserId($tgroup);
        if ($collage->removeEntry($tgroup)) {
            $collage->logger()->general(
                "Collage {$collage->id()} ({$collage->name()}) group entry {$tgroup->id()} (added by user $userId) removed by {$Viewer->username()}"
            );
        }
    } else {
        header("Location: {$collage->location()}");
        exit;
    }
}
$collage->flush();

header("Location: {$collage->location()}&action=manage");
