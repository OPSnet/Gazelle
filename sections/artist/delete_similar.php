<?php
authorize();
if (!check_perms('site_delete_tag')) {
    error(403);
}

$SimilarID = (int)$_GET['similarid'];
$PrimaryArtistID = (int)$_GET['artistid'];
if (!$PrimaryArtistID || !$SimilarID) {
    error(404);
}

$DB->prepared_query("
    SELECT ArtistID
    FROM artists_similar
    WHERE SimilarID = ?
    ", $SimilarID
);
$ArtistIDs = $DB->collect(0);

$DB->prepared_query("
    DELETE FROM artists_similar WHERE SimilarID = ?
    ", $SimilarID
);
$DB->prepared_query("
    DELETE FROM artists_similar_scores WHERE SimilarID = ?
    ", $SimilarID
);
$DB->prepared_query("
    DELETE FROM artists_similar_votes WHERE SimilarID = ?
    ", $SimilarID
);

foreach ($ArtistIDs as $ArtistID) {
    $artist = new \Gazelle\Artist($ArtistID);
    $artist->flushCache();
    $Cache->delete_value("similar_positions_$ArtistID");
}

header("Location: " . redirectUrl("artist.php?id={$PrimaryArtistID}"));
