<?php
$ArtistID = $_GET['artistid'];
$GroupID = $_GET['groupid'];
$Importance = $_GET['importance'];

if (!intval($ArtistID) || !intval($GroupID) || !intval($Importance)) {
    error(404);
}
if (!check_perms('torrents_edit')) {
    error(403);
}

$DB->prepared_query('
    DELETE FROM torrents_artists
    WHERE GroupID = ?
        AND ArtistID = ?
        AND Importance = ?
    ', $GroupID, $ArtistID, $Importance
);
echo "$GroupID, $ArtistID, $Importance.<br />";
$DB->prepared_query('
    SELECT Name
    FROM artists_group
    WHERE ArtistID = ?
    ', $ArtistID
);
list($ArtistName) = $DB->next_record(MYSQLI_NUM, false);

$DB->prepared_query('
    SELECT Name
    FROM torrents_group
    WHERE ID = ?
    ', $GroupID
);
if (!$DB->has_results()) {
    error(404);
}
list($GroupName) = $DB->next_record(MYSQLI_NUM, false);

// Get a count of how many groups or requests use this artist ID
$DB->prepared_query('
    SELECT count(*)
    FROM artists_group AS ag
    LEFT JOIN requests_artists AS ra USING (ArtistID)
    WHERE ra.ArtistID IS NOT NULL
        AND ag.ArtistID = ?
    ', $ArtistID
);
list($ReqCount) = $DB->next_record(MYSQLI_NUM, false);
$DB->prepared_query('
    SELECT count(*)
    FROM artists_group AS ag
    LEFT JOIN torrents_artists AS ta USING (ArtistID)
    WHERE ta.ArtistID IS NOT NULL
        AND ag.ArtistID = ?
    ', $ArtistID
);
list($GroupCount) = $DB->next_record(MYSQLI_NUM, false);
if (($ReqCount + $GroupCount) == 0) {
    // The only group to use this artist
    Artists::delete_artist($ArtistID);
}

$Cache->delete_value("torrents_details_$GroupID"); // Delete torrent group cache
$Cache->delete_value("groups_artists_$GroupID"); // Delete group artist cache
Misc::write_log('Artist ('.$ArtistTypes[$Importance].") $ArtistID ($ArtistName) was removed from the group $GroupID ($GroupName) by user ".$LoggedUser['ID'].' ('.$LoggedUser['Username'].')');
Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "removed artist $ArtistName (".$ArtistTypes[$Importance].')', 0);

Torrents::update_hash($GroupID);
$Cache->delete_value("artist_groups_$ArtistID");

$Location = (empty($_SERVER['HTTP_REFERER'])) ? "torrents.php?id={$GroupID}" : $_SERVER['HTTP_REFERER'];
header("Location: {$Location}");
