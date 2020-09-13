<?php

authorize();

$Val = new Validate;

if (!($_REQUEST['action'] == 'add_torrent' || $_REQUEST['action'] == 'add_torrent_batch')) {
    error(403);
}

$CollageID = (int)$_POST['collageid'];
if (!$CollageID) {
    error(404);
}
$Collage = new Gazelle\Collage($CollageID);

if (!check_perms('site_collages_delete')) {
    if ($Collage->isLocked()) {
        $Err = 'This collage is locked';
    }
    if ($Collage->categoryId() == 0 && !$Collage->isOwner($LoggedUser['ID'])) {
        $Err = 'You cannot edit someone else\'s personal collage.';
    }
    if ($Collage->maxGroups() > 0 && $Collage->numEntries() >= $Collage->maxGroups()) {
        $Err = 'This collage already holds its maximum allowed number of entries.';
    }

    if (isset($Err)) {
        error($Err);
    }
}

/* grab the URLs (single or many) from the form */
$URL = [];
if ($_REQUEST['action'] == 'add_torrent') {
    $URL[] = trim($_POST['url']);
} elseif ($_REQUEST['action'] == 'add_torrent_batch') {
    foreach (explode("\n", $_REQUEST['urls']) as $u) {
        $u = trim($u);
        if (strlen($u)) {
            $URL[] = $u;
        }
    }
}

/* check that they correspond to torrent pages */
$Torrent = [];
foreach ($URL as $u) {
    preg_match('/^'.TORRENT_GROUP_REGEX.'/i', $u, $match);
    $GroupID = $match[4];
    if (!$GroupID || (int)$GroupID === 0) {
        $safe = htmlspecialchars($u);
        error("The entered url ($safe) does not correspond to a torrent page on site.");
    }
    $group_id = $DB->scalar('
        SELECT ID
        FROM torrents_group
        WHERE ID = ?
        ', $GroupID
    );
    if (!$group_id) {
        $safe = htmlspecialchars($GroupID);
        error('The torrent ($safe) does not exist.');
    }
    $Torrent[] = $group_id;
}

if (!check_perms('site_collages_delete')) {
    $maxGroupsPerUser = $Collage->maxGroupsPerUser();
    if ($maxGroupsPerUser > 0) {
        if ($Collage->countByUser($LoggedUser['ID']) + count($ID) > $maxGroupsPerUser) {
            $Err = "You may add no more than $maxGroupsPerUser entries to this collage.";
        }
    }

    $maxGroups = $Collage->maxGroups();
    if ($maxGroups > 0 && ($Collage->numEntries() + count($ID) > $maxGroups)) {
        $Err = "This collage can hold only $maxGroups entries.";
    }

    if (isset($Err)) {
        error($Err);
    }
}

foreach ($Torrent as $group_id) {
    $Collage->addTorrent($group_id, $LoggedUser['ID']);
}
header("Location: collages.php?id=$CollageID");
