<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!in_array($_POST['action'], ['add_artist', 'add_artist_batch'])) {
    Error403::error();
}

$collageMan = new Manager\Collage();
$collage    = null;
if (isset($_POST['collage_combo'])) {
    // From artist page
    $collage = $collageMan->findById((int)$_POST['collage_combo']);
}
if (is_null($collage)) {
    if (isset($_POST['collageid'])) {
        // From collage page
        $collage = $collageMan->findById((int)$_POST['collageid']);
    } elseif (isset($_POST['collage_ref'])) {
        // From add artist widget
        $ref = trim($_POST['collage_ref']);
        $collage = $collageMan->findByName($ref);
        if (is_null($collage) && preg_match('@' . SITE_URL . '/collages\.php.*?(?:id=)?(\d+)(?:&|\s*$)?@', $ref, $match)) {
            $collage = $collageMan->findById((int)$match[1]);
        }
    }
}
if (is_null($collage)) {
    Error404::error("Did not identify any artists");
}

if (!$Viewer->permitted('site_collages_delete')) {
    if ($collage->isLockedForUser($Viewer)) {
        Error400::error('This collage is locked');
    }
    if ($collage->isPersonal() && !$collage->isOwner($Viewer)) {
        Error400::error("You cannot edit someone else's personal collage.");
    }
    if ($collage->maxGroups() > 0 && $collage->total() >= $collage->maxGroups()) {
        Error400::error('This collage already holds its maximum allowed number of entries.');
    }
}

/* grab the URLs (single or many) from the form */
$URL = [];
if ($_REQUEST['action'] == 'add_artist') {
    if (isset($_POST['url'])) {
        // From a collage page
        $URL[] = trim($_POST['url']);
    } elseif (isset($_POST['artistid'])) {
        // From an artist page
        $URL[] = SITE_URL . '/artist.php?id=' . (int)$_POST['artistid'];
    } elseif (isset($_POST['entryid'])) {
        // From an autocomplete
        $URL[] = SITE_URL . '/artist.php?id=' . (int)$_POST['entryid'];
    }
} elseif ($_REQUEST['action'] == 'add_artist_batch') {
    foreach (explode("\n", $_REQUEST['urls']) as $u) {
        $u = trim($u);
        if (strlen($u)) {
            $URL[] = $u;
        }
    }
}

/* check that they correspond to artist pages */
$artistMan = new Manager\Artist();
$list = [];
foreach ($URL as $u) {
    $artist = preg_match(ARTIST_REGEXP, $u, $match)
        ? $artistMan->findById((int)$match['id'])
        : null;
    if (is_null($artist)) {
        Error400::error("The artist " . htmlspecialchars($u) . " does not exist.");
    }
    $list[] = $artist;
}

/* would the addition overshoot the allowed number of entries? */
if (!$Viewer->permitted('site_collages_delete')) {
    $maxGroupsPerUser = $collage->maxGroupsPerUser();
    if ($maxGroupsPerUser > 0) {
        if ($collage->contributionTotal($Viewer) + count($list) > $maxGroupsPerUser) {
            Error400::error(
                "You may add no more than $maxGroupsPerUser entries to this collage."
            );
        }
    }

    $maxGroups = $collage->maxGroups();
    if ($maxGroups > 0 && ($collage->total() + count($list) > $maxGroups)) {
        Error400::error("This collage can hold only $maxGroups entries.");
    }
}

foreach ($list as $artist) {
    $collage->addEntry($artist, $Viewer);
}
$collageMan->flushDefaultArtist($Viewer);
header('Location: ' . $collage->location());
