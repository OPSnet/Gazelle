<?php

if ($Viewer->auth() != $_POST['auth']) {
    json_die('failure', 'auth');
}
if (!$Viewer->permitted('site_collages_manage') && !$Viewer->activePersonalCollages()) {
    json_die('failure', 'access');
}

$collageMan = new Gazelle\Manager\Collage;
$collage = $collageMan->findById((int)($_POST['collage_id'] ?? 0));
if (is_null($collage)) {
    if (preg_match(COLLAGE_REGEXP, $_POST['name'], $match)) {
        // Looks like a URL
        $collage = $collageMan->findById((int)$match['id']);
    }
    if (is_null($collage)) {
        // Must be a name of a collage
        $collage = $collageMan->findByName($_POST['name'] ?? '');
    }
    if (is_null($collage)) {
        json_die('failure', 'collage not found');
    }
}

if (!isset($_POST['entry_id'])) {
    json_die('failure', 'entry not specified');
} else {
    $manager = $collage->isArtist() ? new Gazelle\Manager\Artist : new Gazelle\Manager\TGroup;
    $entry   = $manager->findById((int)$_POST['entry_id']);
    if (is_null($entry)) {
        json_die('failure', 'entry not found');
    }
}

if (!$Viewer->permitted('site_collages_delete')) {
    if ($collage->isLocked()) {
        json_die('failure', 'locked');
    }
    if ($collage->isPersonal() && !$collage->isOwner($Viewer->id())) {
        json_die('failure', 'personal');
    }
    if ($collage->maxGroups() > 0 && $collage->numEntries() >= $collage->maxGroups()) {
        json_die('failure', 'max entries reached');
    }
    $maxGroupsPerUser = $collage->maxGroupsPerUser();
    if ($maxGroupsPerUser > 0) {
        if ($collage->countByUser($Viewer->id()) >= $maxGroupsPerUser) {
            json_die('failure', 'you have already contributed');
        }
    }
}

if (!$collage->addEntry($entry->id(), $Viewer->id())) {
    json_die('failure', 'already present?');
}

if ($collage->isArtist()) {
    $collageMan->flushDefaultArtist($Viewer->id());
} else {
    $collageMan->flushDefaultGroup($Viewer->id());
}
json_print('success', ['link' => $collage->link()]);
