<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_collages_manage')) {
    Error403::error();
}

authorize();

$collage = new Manager\Collage()->findById((int)($_POST['collageid']));
if (is_null($collage)) {
    Error404::error("Cannot find the requested collage");
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    Error403::error();
}

if (isset($_POST['drag_drop_collage_sort_order'])) {
    $collage->updateSequence($_POST['drag_drop_collage_sort_order']);
} elseif (isset($_POST['groupid'])) {
    $tgroup = new Manager\TGroup()->findById((int)($_POST['groupid'] ?? 0));
    if (is_null($tgroup)) {
        Error404::error("Cannot find torrent group");
    }
    if (isset($_POST['sort'])) {
        $collage->updateSequenceEntry($tgroup, (int)$_POST['sort']);
    }

    if ($_POST['submit'] === 'Remove') {
        $userId = $collage->entryUserId($tgroup);
        if ($collage->removeEntry($tgroup)) {
            $collage->logger()->general(
                "Collage {$collage->id} ({$collage->name()}) group entry {$tgroup->id} (added by user $userId) removed by {$Viewer->username()}"
            );
        }
    } else {
        header("Location: {$collage->location()}");
        return;
    }
}
$collage->flush();

header("Location: {$collage->location()}&action=manage");
