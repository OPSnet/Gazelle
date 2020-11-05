<?php

authorize();

$Val = new Validate;

if (!($_REQUEST['action'] == 'add_artist' || $_REQUEST['action'] == 'add_artist_batch')) {
    error(403);
}

$CollageID = $_POST['collageid'];
if (!is_number($CollageID)) {
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
if ($_REQUEST['action'] == 'add_artist') {
    $URL[] = trim($_POST['url']);
}
elseif ($_REQUEST['action'] == 'add_artist_batch') {
    foreach (explode("\n", $_REQUEST['urls']) as $u) {
        $u = trim($u);
        if (strlen($u)) {
            $URL[] = $u;
        }
    }
}

/* check that they correspond to artist pages */
$ID = [];
foreach ($URL as $u) {
    if (!preg_match('/^'.ARTIST_REGEX.'/i', $u, $match)) {
        $safe = htmlspecialchars($u);
        error("The entered url ($safe) does not correspond to an artist page on site.");
    }
    $ArtistID = end($match);
    try {
        $artist = new Gazelle\Artist($ArtistID);
    }
    catch (Exception $e) {
        $safe = htmlspecialchars($u);
        error("The entered url ($safe) does not correspond to an artist page on site.");
    }
    $ID[] = $ArtistID;
}

/* would the addition overshoot the allowed number of entries? */
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

foreach ($ID as $artistId) {
    $Collage->addArtist($artistId, $LoggedUser['ID']);
}
header("Location: collages.php?id=$CollageID");
