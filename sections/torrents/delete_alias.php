<?php

if (!check_perms('torrents_edit')) {
    error(403);
}

$ArtistID = $_GET['artistid'];
$GroupID = $_GET['groupid'];
$Importance = $_GET['importance'];

if (!intval($ArtistID) || !intval($GroupID) || !intval($Importance)) {
    error(404);
}
$GroupName = $DB->scalar('SELECT Name FROM torrents_group WHERE ID = ?', $GroupID);
if (!$GroupName) {
    error(404);
}

$DB->prepared_query('
    DELETE FROM torrents_artists
    WHERE GroupID = ?
        AND ArtistID = ?
        AND Importance = ?
    ', $GroupID, $ArtistID, $Importance
);

$ArtistName = $DB->scalar('SELECT Name FROM artists_group WHERE ArtistID = ?', $ArtistID);

// Get a count of how many groups or requests use this artist ID
$ReqCount = $DB->scalar('
    SELECT count(*)
    FROM artists_group AS ag
    INNER JOIN requests_artists AS ra USING (ArtistID)
    WHERE ag.ArtistID = ?
    ', $ArtistID
);
$GroupCount = $DB->scalar('
    SELECT count(*)
    FROM artists_group AS ag
    INNER JOIN torrents_artists AS ta USING (ArtistID)
    WHERE ag.ArtistID = ?
    ', $ArtistID
);
if (($ReqCount + $GroupCount) == 0) {
    // The only group to use this artist
    Artists::delete_artist($ArtistID);
}

$Cache->deleteMulti(["torrents_details_$GroupID", "groups_artists_$GroupID", "artist_groups_$ArtistID"]);

(new Gazelle\Log)->group($GroupID, $Viewer->id(), "removed artist $ArtistName (".$ArtistTypes[$Importance].')')
    ->general('Artist ('.$ArtistTypes[$Importance]
        . ") $ArtistID ($ArtistName) was removed from the group $GroupID ($GroupName) by user "
        . $Viewer->id().' ('.$Viewer->username().')');
Torrents::update_hash($GroupID);

header("Location: " . $_SERVER['HTTP_REFERER'] ?? "torrents.php?id={$GroupID}");
