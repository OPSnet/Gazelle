<?php
if (!check_perms('torrents_edit')) {
    error(403);
}
authorize();

if (empty($_POST['importance']) || empty($_POST['artists']) || empty($_POST['groupid']) || !is_number($_POST['importance']) || !is_number($_POST['groupid'])) {
    error(0);
}

$GroupID = (int)$_POST['groupid'];
if (!$GroupID) {
    error(404);
}
$Artists = explode(',', $_POST['artists']);

$CleanArtists = [];
$ArtistIDs = [];
$ArtistsString = '0';

foreach ($Artists as $i => $Artist) {
    [$Importance, $ArtistID] = explode(';', $Artist);
    if ($ArtistID && $Importance) {
        $CleanArtists[] = [(int)$Importance, (int)$ArtistID];
        $ArtistIDs[] = (int)$ArtistID;
    }
}

if (count($CleanArtists) > 0) {
    $GroupName = $DB->scalar('
        SELECT Name
        FROM torrents_group
        WHERE ID = ?
        ', $GroupID
    );
    $placeholders = placeholders($ArtistIDs);
    $DB->prepared_query("
        SELECT ArtistID, Name
        FROM artists_group
        WHERE ArtistID IN ($placeholders)
        ", ...$ArtistIDs
    );
    $ArtistNames = $DB->to_array('ArtistID', MYSQLI_ASSOC, false);
    if ($_POST['manager_action'] == 'delete') {
        $logger = new Gazelle\Log;
        foreach ($CleanArtists as $Artist) {
            [$Importance, $ArtistID] = $Artist;
            $DB->prepared_query("
                DELETE FROM torrents_artists
                WHERE GroupID = ?
                    AND ArtistID = ?
                    AND Importance = ?
                ", $GroupID, $ArtistID, $Importance
            );
            if ($DB->affected_rows()) {
                $artistInfo = "$ArtistID ({$ArtistNames[$ArtistID]['Name']} as {$ArtistTypes[$Importance]})";
                $logger->group($GroupID, $Viewer->id(), "Removed artist $artistInfo")
                    ->general("Artist $artistInfo was removed from the group $GroupID ($GroupName) by user {$LoggedUser['ID']} ('{$LoggedUser['Username']}')");
                $Cache->delete_value("artist_groups_$ArtistID");
            }
        }
        $DB->prepared_query("
            SELECT ArtistID
                FROM requests_artists
                WHERE ArtistID IN ($placeholders)
            UNION DISTINCT
            SELECT ArtistID
                FROM torrents_artists
                WHERE ArtistID IN ($placeholders)
            ", ...array_merge($ArtistIDs, $ArtistIDs)
        );
        $Items = $DB->collect('ArtistID');
        $EmptyArtists = array_diff($ArtistIDs, $Items);
        foreach ($EmptyArtists as $ArtistID) {
            Artists::delete_artist($ArtistID);
        }
    }
    else {
        $NewImportance = (int)$_POST['importance'];
        if ($NewImportance === 0 || !isset($ArtistTypes[$NewImportance])) {
            error(0);
        }
        $DB->prepared_query("
            UPDATE IGNORE torrents_artists SET
                Importance = ?
            WHERE GroupID = ?
                AND ArtistID IN ($placeholders)
            ", $NewImportance, $GroupID, ...$ArtistIDs
        );
        foreach ($CleanArtists as $Artist) {
            [$Importance, $ArtistID] = $Artist;
            // Don't bother logging artists whose importance hasn't changed
            if ($Importance === $NewImportance) {
                // continue;
            }
            (new Gazelle\Log)->group($GroupID, $Viewer->id(), "Importance changed artist {$ArtistNames[$ArtistID]['Name']} ({$ArtistTypes[$Importance]}) to {$ArtistTypes[$NewImportance]}")
                ->general("Artist ({$ArtistTypes[$Importance]}) $ArtistID ({$ArtistNames[$ArtistID]['Name']})"
                    . " importance was changed to {$ArtistTypes[$NewImportance]} in group {$GroupID} ({$GroupName}) by user {$LoggedUser['ID']} ({$LoggedUser['Username']})");
        }
    }
    $Cache->delete_value("groups_artists_$GroupID");
    Torrents::update_hash($GroupID);
    header("Location: torrents.php?id=$GroupID");
}
