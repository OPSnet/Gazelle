<?php

if (!$Viewer->permitted('torrents_edit')) {
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

foreach ($Artists as $Artist) {
    [$Importance, $ArtistID] = explode(';', $Artist);
    if ($ArtistID && $Importance) {
        $CleanArtists[] = [(int)$Importance, (int)$ArtistID];
        $ArtistIDs[] = (int)$ArtistID;
    }
}

if (count($CleanArtists) > 0) {
    $db = Gazelle\DB::DB();
    $GroupName = $db->scalar('
        SELECT Name FROM torrents_group WHERE ID = ?
        ', $GroupID
    );
    $placeholders = placeholders($ArtistIDs);
    $db->prepared_query("
        SELECT ArtistID, Name
        FROM artists_group
        WHERE ArtistID IN ($placeholders)
        ", ...$ArtistIDs
    );
    $ArtistNames = $db->to_array('ArtistID', MYSQLI_ASSOC, false);
    if ($_POST['manager_action'] == 'delete') {
        $logger = new Gazelle\Log;
        foreach ($CleanArtists as $Artist) {
            [$Importance, $ArtistID] = $Artist;
            $db->prepared_query("
                DELETE FROM torrents_artists
                WHERE GroupID = ?
                    AND ArtistID = ?
                    AND Importance = ?
                ", $GroupID, $ArtistID, $Importance
            );
            if ($db->affected_rows()) {
                $change = "artist $ArtistID ({$ArtistNames[$ArtistID]['Name']}) removed as " . ARTIST_TYPE[$Importance];
                $logger->group($GroupID, $Viewer->id(), $change)
                    ->general("$change in group {$GroupID} ({$GroupName}) by user "
                        . $Viewer->id() . " (" . $Viewer->username() . ")"
                    );
                $Cache->delete_value("artist_groups_$ArtistID");
            }
        }
        $db->prepared_query("
            SELECT ArtistID
                FROM requests_artists
                WHERE ArtistID IN ($placeholders)
            UNION DISTINCT
            SELECT ArtistID
                FROM torrents_artists
                WHERE ArtistID IN ($placeholders)
            ", ...[...$ArtistIDs, ...$ArtistIDs]
        );
        $Items = $db->collect('ArtistID');
        $EmptyArtists = array_diff($ArtistIDs, $Items);
        $logger = new Gazelle\Log;
        foreach ($EmptyArtists as $ArtistID) {
            (new Gazelle\Artist($ArtistID))->remove($Viewer, $logger);
        }
    }
    else {
        $NewImportance = (int)$_POST['importance'];
        if ($NewImportance === 0 || !isset(ARTIST_TYPE[$NewImportance])) {
            error(0);
        }
        $db->prepared_query("
            UPDATE IGNORE torrents_artists SET
                Importance = ?
            WHERE GroupID = ?
                AND ArtistID IN ($placeholders)
            ", $NewImportance, $GroupID, ...$ArtistIDs
        );
        $logger = new Gazelle\Log;
        foreach ($CleanArtists as $Artist) {
            [$Importance, $ArtistID] = $Artist;
            // Don't bother logging artists whose importance hasn't changed
            if ($Importance === $NewImportance) {
                // continue;
            }
            $change = "artist $ArtistID ({$ArtistNames[$ArtistID]['Name']}) changed role from "
                . ARTIST_TYPE[$Importance] . " to " . ARTIST_TYPE[$NewImportance];
            $logger->group($GroupID, $Viewer->id(), $change)
                ->general("$change in group {$GroupID} ({$GroupName}) by user " . $Viewer->label());
        }
    }
    (new \Gazelle\Manager\TGroup)->findById($GroupID)?->refresh();
    header("Location: torrents.php?id=$GroupID");
}
