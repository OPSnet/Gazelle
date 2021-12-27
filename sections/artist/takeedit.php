<?php
/*********************************************************************\
The page that handles the backend of the 'edit artist' function.
\*********************************************************************/

if (!$Viewer->permitted('site_edit_wiki')) {
    error(403);
}

authorize();

$artist = (new Gazelle\Manager\Artist)->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    error(404);
}
$artistId = $artist->id();

// Variables for database input
$userId   = $Viewer->id();
$summary  = [];

if ($_GET['action'] === 'revert') { // if we're reverting to a previous revision
    authorize();
    $revisionId = $_GET['revisionid'];
    if (!is_number($revisionId)) {
        error(0);
    }
} else { // with edit, the variables are passed with POST
    $discogsId = (int)($_POST['discogs-id']);
    $body      = trim($_POST['body']);
    $summary[] = trim($_POST['summary']);
    $image     = trim($_POST['image']);
    $locked    = $_POST['locked'] ?? false;
    $unlocked  = $_POST['unlocked'] ?? false;
}

if (!empty($image)) {
    if (!preg_match(IMAGE_REGEXP, $image)) {
        error(display_str($image) . " does not look like a valid image url");
    }
    $banned = (new Gazelle\Util\ImageProxy)->badHost($image);
    if ($banned) {
        error("Please rehost images from $banned elsewhere.");
    }
}

if ($discogsId > 0) {
    if ($discogsId != $artist->discogsId() && $artist->setDiscogsRelation($discogsId, $userId)) {
        $summary[] = "Discogs relation set to $discogsId";
    }
} elseif (!$discogsId && $artist->discogsId()) {
    $artist->removeDiscogsRelation();
    $summary[] = "Discogs relation cleared";
}

// Insert revision
if (!$revisionId) { // edit
    $DB->prepared_query("
        INSERT INTO wiki_artists
               (PageID, Body, Image, UserID, Summary)
        VALUES (?,      ?,    ?,     ?,      ?)
        ", $artistId, $body, $image, $userId, implode(', ', $summary)
    );
    $revisionId = $DB->inserted_id();
} else { // revert
    $DB->prepared_query("
        INSERT INTO wiki_artists
              (PageID, Body, Image, UserID, Summary)
        SELECT ?,      Body, Image, ?,      ?
        FROM wiki_artists
        WHERE revisionId = ?
        ", $artistId, $userID, "Reverted to revision $revisionId",
            $revisionId
    );
}

// Update artists table (technically, we don't need the revisionId column, but we can use it for a join which is nice and fast)
$column = ['RevisionID = ?'];
$args   = [$revisionId];
if ($Viewer->permitted('artist_edit_vanityhouse')) {
    $column[] = 'VanityHouse = ?';
    $args[] = isset($_POST['showcase']) ? 1 : 0;
}

$columns = implode(', ', $column);
$args[] = $artistId;
$DB->prepared_query($sql = "
    UPDATE artists_group SET
        $columns
    WHERE ArtistID = ?
    ", ...$args
);

if ($locked) {
    $artist->setLocked();
} elseif ($unlocked && check_perms('users_mod')) {
    $artist->setUnlocked();
}

// There we go, all done!
$artist->flushCache();
header("Location: artist.php?id={$artistId}");
