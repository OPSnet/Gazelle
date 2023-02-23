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
$summary  = [];
$db       = Gazelle\DB::DB();

if (($_GET['action'] ?? '') === 'revert') { // if we're reverting to a previous revision
    authorize();
    $revisionId = (int)$_GET['revisionid'];
    if (!$revisionId) {
        error(0);
    }

    $db->prepared_query("
        INSERT INTO wiki_artists
              (PageID, Body, Image, UserID, Summary)
        SELECT ?,      Body, Image, ?,      ?
        FROM wiki_artists
        WHERE revisionId = ?
        ", $artistId, $Viewer->id(), "Reverted to revision $revisionId",
            $revisionId
    );

    $locked    = false;
    $unlocked  = false;

} else { // with edit, the variables are passed with POST
    $discogsId = (int)($_POST['discogs-id']);
    $body      = trim($_POST['body']);
    $summary[] = trim($_POST['summary']);
    $image     = trim($_POST['image']);
    $locked    = $_POST['locked'] ?? false;
    $unlocked  = $_POST['unlocked'] ?? false;

    if ($image) {
        if (!preg_match(IMAGE_REGEXP, $image)) {
            error(display_str($image) . " does not look like a valid image url");
        }
        $banned = (new Gazelle\Util\ImageProxy($Viewer))->badHost($image);
        if ($banned) {
            error("Please rehost images from $banned elsewhere.");
        }
    }

    if ($discogsId > 0) {
        if ($discogsId != $artist->discogsId() && $artist->setDiscogsRelation($discogsId, $Viewer->id())) {
            $summary[] = "Discogs relation set to $discogsId";
        }
    } elseif (!$discogsId && $artist->discogsId()) {
        $artist->removeDiscogsRelation();
        $summary[] = "Discogs relation cleared";
    }

    $db->prepared_query("
        INSERT INTO wiki_artists
               (PageID, Body, Image, UserID, Summary)
        VALUES (?,      ?,    ?,     ?,      ?)
        ", $artistId, $body, $image, $Viewer->id(), implode(', ', $summary)
    );
    $revisionId = $db->inserted_id();
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
$db->prepared_query($sql = "
    UPDATE artists_group SET
        $columns
    WHERE ArtistID = ?
    ", ...$args
);

if ($locked) {
    $artist->setLocked();
} elseif ($unlocked && $Viewer->permitted('users_mod')) {
    $artist->setUnlocked();
}

// There we go, all done!
$artist->flushCache();
header("Location: " . $artist->location());
