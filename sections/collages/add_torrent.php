<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!in_array($_REQUEST['action'], ['add_torrent', 'add_torrent_batch'])) {
    Error403::error();
}
if (!$Viewer->permitted('site_collages_manage') && !$Viewer->activePersonalCollages()) {
    Error403::error();
}

$collageMan = new Manager\Collage();
if (isset($_POST['collage_combo']) && (int)$_POST['collage_combo']) {
    $collage = $collageMan->findById((int)$_POST['collage_combo']); // From release page
} elseif (isset($_POST['collage_ref'])) {
    $collage = $collageMan->findByName(trim($_POST['collage_ref'])); // From release page (autocomplete)
    // maybe it was an URL
    if (is_null($collage) && preg_match(COLLAGE_REGEXP, $_POST['collage_ref'], $match)) {
        $collage = $collageMan->findById((int)$match['id']);
    }
} else {
    $collage = $collageMan->findById((int)$_POST['collageid']); // From collage page
}
if (!$collage) {
    Error404::error();
}

if (!$Viewer->permitted('site_collages_delete')) {
    if ($collage->isLockedForUser($Viewer)) {
        Error400::error('This collage is locked');
    }
    if ($collage->isPersonal() && !$collage->isOwner($Viewer)) {
        Error400::error('You cannot edit someone else\'s personal collage.');
    }
    if ($collage->maxGroups() > 0 && $collage->total() >= $collage->maxGroups()) {
        Error400::error('This collage already holds its maximum allowed number of entries.');
    }
}

/* grab the URLs (single or many) from the form */
$URL = [];
if ($_REQUEST['action'] == 'add_torrent') {
    if (isset($_POST['url'])) {
        // From a collage page
        $URL[] = trim($_POST['url']);
    } elseif (isset($_POST['groupid'])) {
        // From a release page
        $URL[] = SITE_URL . '/torrents.php?id=' . (int)$_POST['groupid'];
    } elseif (isset($_POST['entryid'])) {
        $URL[] = SITE_URL . '/torrents.php?id=' . (int)$_POST['entryid'];
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
$tgroupMan = new Manager\TGroup();
$list = [];
foreach ($URL as $u) {
    preg_match(TGROUP_REGEXP, $u, $match);
    $tgroup = $tgroupMan->findById((int)($match['id'] ?? 0));
    if (is_null($tgroup)) {
        Error400::error("The torrent " . htmlspecialchars($u) . " does not exist.");
    }
    $list[] = $tgroup;
}

if (!$Viewer->permitted('site_collages_delete')) {
    $maxGroupsPerUser = $collage->maxGroupsPerUser();
    if ($maxGroupsPerUser > 0) {
        if ($collage->contributionTotal($Viewer) + count($list) > $maxGroupsPerUser) {
            $entry = $maxGroupsPerUser === 1 ? 'entry' : 'entries';
            Error400::error(
                "You may add no more than $maxGroupsPerUser $entry to this collage."
            );
        }
    }

    $maxGroups = $collage->maxGroups();
    if ($maxGroups > 0 && ($collage->total() + count($list) > $maxGroups)) {
        $entry = $maxGroupsPerUser === 1 ? 'entry' : 'entries';
        Error400::error("This collage can hold only $maxGroups $entry.");
    }
}

foreach ($list as $tgroup) {
    $collage->addEntry($tgroup, $Viewer);
}
$collageMan->flushDefaultGroup($Viewer);
header('Location: ' . $collage->location());
