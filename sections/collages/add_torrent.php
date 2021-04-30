<?php

authorize();

if (!($_REQUEST['action'] == 'add_torrent' || $_REQUEST['action'] == 'add_torrent_batch')) {
    error(403);
}

$collageMan = new Gazelle\Manager\Collage;
if (isset($_POST['collage_combo']) && (int)$_POST['collage_combo']) {
    $collage = $collageMan->findById((int)$_POST['collage_combo']); // From release page
} elseif (isset($_POST['collage_ref'])) {
    $collage = $collageMan->findByName(trim($_POST['collage_ref'])); // From release page (autocomplete)
} else {
    $collage = $collageMan->findById((int)$_POST['collageid']); // From collage page
}
if (!$collage) {
    error(404);
}

if (!check_perms('site_collages_delete')) {
    if ($collage->isLocked()) {
        error('This collage is locked');
    }
    if ($collage->categoryId() == 0 && !$collage->isOwner($LoggedUser['ID'])) {
        error('You cannot edit someone else\'s personal collage.');
    }
    if ($collage->maxGroups() > 0 && $collage->numEntries() >= $collage->maxGroups()) {
        error('This collage already holds its maximum allowed number of entries.');
    }
}

/* grab the URLs (single or many) from the form */
$URL = [];
if ($_REQUEST['action'] == 'add_torrent') {
    if (isset($_POST['url'])) {
        // From a collage page
        $URL[] = trim($_POST['url']);
    } elseif (isset($_POST['groupid'])) {
        // From a relase page
        $URL[] = SITE_URL . '/torrents.php?id=' . (int)$_POST['groupid'];
    }
} elseif ($_REQUEST['action'] == 'add_torrent_batch') {
    foreach (explode("\n", $_REQUEST['urls']) as $u) {
        $u = trim($u);
        if (strlen($u)) {
            $URL[] = $u;
        }
    }
}

/* check that they correspond to torrent pages */
$groupIds = [];
foreach ($URL as $u) {
    preg_match('/^'.TORRENT_GROUP_REGEX.'/i', $u, $match);
    $GroupID = end($match);
    if (!$GroupID || (int)$GroupID === 0) {
        $safe = htmlspecialchars($u);
        error("The entered url ($safe) does not correspond to a torrent page on site." .TORRENT_GROUP_REGEX. '');
    }
    $id = $DB->scalar("
        SELECT ID FROM torrents_group WHERE ID = ?
        ", $GroupID
    );
    if (!$id) {
        $safe = htmlspecialchars($GroupID);
        error('The torrent ($safe) does not exist.');
    }
    $groupIds[] = $id;
}

if (!check_perms('site_collages_delete')) {
    $maxGroupsPerUser = $collage->maxGroupsPerUser();
    if ($maxGroupsPerUser > 0) {
        if ($collage->countByUser($LoggedUser['ID']) + count($ID) > $maxGroupsPerUser) {
            error("You may add no more than $maxGroupsPerUser entries to this collage.");
        }
    }

    $maxGroups = $collage->maxGroups();
    if ($maxGroups > 0 && ($collage->numEntries() + count($ID) > $maxGroups)) {
        error("This collage can hold only $maxGroups entries.");
    }
}

foreach ($groupIds as $groupId) {
    $collage->addTorrent($groupId, $LoggedUser['ID']);
}
$collageMan->flushDefaultGroup($LoggedUser['ID']);
header("Location: collages.php?id=" . $collage->id());
