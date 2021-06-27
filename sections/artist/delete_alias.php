<?php
authorize();
if (!check_perms('torrents_edit')) {
    error(403);
}

$AliasID = (int)$_GET['aliasid'];
if (!$AliasID) {
    error(404);
}

if ($DB->scalar("
    SELECT aa.AliasID
    FROM artists_alias AS aa
    INNER JOIN artists_alias AS aa2 USING (ArtistID)
    WHERE aa.AliasID = ?
    ", $AliasID
) == 1) {
    error("The alias $AliasID is the last alias for this artist; removing it would cause bad things to happen.");
}

$GroupID = $DB->scalar("
    SELECT GroupID
    FROM torrents_artists
    WHERE AliasID = ?
    ", $AliasID);
if ($GroupID) {
    error("The alias $AliasID still has the group (<a href=\"torrents.php?id=$GroupID\">$GroupID</a>) attached. Fix that first.");
}

[$ArtistID, $ArtistName, $AliasName] = $DB->row("
    SELECT aa.ArtistID, ag.Name, aa.Name
    FROM artists_alias AS aa
    INNER JOIN artists_group AS ag USING (ArtistID)
    WHERE aa.AliasID = ?
    ", $AliasID
);

$DB->prepared_query("
    DELETE FROM artists_alias WHERE AliasID = ?
    ", $AliasID
);
$DB->prepared_query("
    UPDATE artists_alias SET
        Redirect = '0'
    WHERE Redirect = ?
    ", $AliasID
);

(new Gazelle\Log)->general(
    "The alias $AliasID ($AliasName) was removed from the artist $ArtistID ($ArtistName) by user "
    . $Viewer->id() . " (" . $Viewer->username() . ")"
);

header("Location: " . redirectUrl("artist.php?action=edit&artistid={$ArtistID}"));
