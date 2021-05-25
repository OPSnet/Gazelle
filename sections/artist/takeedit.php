<?php
/*********************************************************************\
The page that handles the backend of the 'edit artist' function.
\*********************************************************************/

authorize();

if (!$_REQUEST['artistid'] || !is_number($_REQUEST['artistid'])) {
    error(404);
}

if (!check_perms('site_edit_wiki')) {
    error(403);
}

// Variables for database input
$userId   = $LoggedUser['ID'];
$artistId = $_REQUEST['artistid'];
$artist   = new \Gazelle\Artist($artistId);
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
    ImageTools::blacklisted($image);
    if (!preg_match(IMAGE_REGEXP, $image)) {
        error("That does not look like a valid image url");
    }
    foreach (IMAGE_HOST_BANNED as $banned) {
        if (stripos($banned, $image) !== false) {
            error("Please rehost images from $banned elsewhere.");
        }
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
if (check_perms('artist_edit_vanityhouse')) {
    $column[] = 'VanityHouse = ?';
    $args[] = isset($_POST['vanity_house']) ? 1 : 0;
}

$columns = implode(', ', $column);
$args[] = $artistId;
$DB->prepared_query($sql = "
    UPDATE artists_group SET
        $columns
    WHERE ArtistID = ?
    ", ...$args
);

// There we go, all done!
$artist->flushCache();
header("Location: artist.php?id={$artistId}");
