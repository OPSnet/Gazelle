<?php
if (!check_perms('torrents_edit')) {
    error(403);
}
authorize();

if (empty($_POST['importance']) || empty($_POST['artists']) || empty($_POST['groupid']) || !is_number($_POST['importance']) || !is_number($_POST['groupid'])) {
    error(0);
}

$GroupID = intval($_POST['groupid']);
if ($GroupID === 0) {
    error(0);
}
$Artists = explode(',', $_POST['artists']);
$CleanArtists = [];
$ArtistIDs = [];
$ArtistsString = '0';
$LoggedUser = G::$LoggedUser;

foreach ($Artists as $i => $Artist) {
    list($Importance, $ArtistID) = explode(';', $Artist);
    if (is_number($ArtistID) && is_number($Importance)) {
        $CleanArtists[] = [$Importance, $ArtistID];
        $ArtistIDs[] = $ArtistID;
    }
}

if (count($CleanArtists) > 0) {
    $ArtistsString = implode(',', $ArtistIDs);
    $GroupName = $DB->scalar('
        SELECT Name
        FROM torrents_group
        WHERE ID = ?
        ', $GroupID
    );
    $DB->query("
            SELECT ArtistID, Name
            FROM artists_group
            WHERE ArtistID IN ($ArtistsString)");
    $ArtistNames = $DB->to_array('ArtistID', MYSQLI_ASSOC, false);
    if ($_POST['manager_action'] == 'delete') {
        foreach ($CleanArtists as $Artist) {
            list($Importance, $ArtistID) = $Artist;
            Misc::write_log("Artist ({$ArtistTypes[$Importance]}) {$ArtistID} ({$ArtistNames[$ArtistID]['Name']}) was removed from the group {$GroupID} ({$GroupName}) by user {$LoggedUser['ID']} ('{$LoggedUser['Username']}')");
            Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "Removed artist {$ArtistNames[$ArtistID]['Name']} ({$ArtistTypes[$Importance]})", 0);
            $DB->query("
                DELETE FROM torrents_artists
                WHERE GroupID = '{$GroupID}'
                    AND ArtistID = '{$ArtistID}'
                    AND Importance = '{$Importance}'");
            $Cache->delete_value("artist_groups_$ArtistID");
        }
        $DB->query("
            SELECT ArtistID
                FROM requests_artists
                WHERE ArtistID IN ($ArtistsString)
            UNION
            SELECT ArtistID
                FROM torrents_artists
                WHERE ArtistID IN ($ArtistsString)");
        $Items = $DB->collect('ArtistID');
        $EmptyArtists = array_diff($ArtistIDs, $Items);
        foreach ($EmptyArtists as $ArtistID) {
            Artists::delete_artist($ArtistID);
        }
    }
    else {
        $NewImportance = intval($_POST['importance']);
        if ($NewImportance === 0 || !isset($ArtistTypes[$NewImportance])) {
            error(0);
        }
        $DB->query("
            UPDATE IGNORE torrents_artists
            SET Importance = '{$NewImportance}'
            WHERE GroupID = '{$GroupID}'
                AND ArtistID IN ($ArtistsString)");
        foreach ($CleanArtists as $Artist) {
            list($Importance, $ArtistID) = $Artist;
            // Don't bother logging artists whose importance hasn't changed
            if ($Importance === $NewImportance) {
                continue;
            }
            Misc::write_log("Artist ({$ArtistTypes[$Importance]}) $ArtistID ({$ArtistNames[$ArtistID]['Name']}) importance was change to {$ArtistTypes[$NewImportance]} in group {$GroupID} ({$GroupName}) by user {$LoggedUser['ID']} ({$LoggedUser['Username']})");
            Torrents::write_group_log($GroupID, 0, G::$LoggedUser['ID'], "Importance changed artist {$ArtistNames[$ArtistID]['Name']} ({$ArtistTypes[$Importance]}) to {$ArtistTypes[$NewImportance]}", 0);
        }
    }
    $Cache->delete_value("groups_artists_$GroupID");
    Torrents::update_hash($GroupID);
    header("Location: torrents.php?id=$GroupID");
}
