<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

if (empty($_POST['importance']) || empty($_POST['artists']) || empty($_POST['groupid']) || !is_number($_POST['importance']) || !is_number($_POST['groupid'])) {
    error(0);
}

$tgroup = (new Gazelle\Manager\TGroup)->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
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
                ", $tgroup->id(), $ArtistID, $Importance
            );
            if ($db->affected_rows()) {
                $change = "artist $ArtistID ({$ArtistNames[$ArtistID]['Name']}) removed as " . ARTIST_TYPE[$Importance];
                $logger->group($tgroup, $Viewer, $change)
                    ->general("$change in group {$tgroup->id()} ({$tgroup->name()}) by user {$Viewer->label()}");
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
    } else {
        $NewImportance = (int)$_POST['importance'];
        if ($NewImportance === 0 || !isset(ARTIST_TYPE[$NewImportance])) {
            error(0);
        }
        $db->prepared_query("
            UPDATE IGNORE torrents_artists SET
                Importance = ?
            WHERE GroupID = ?
                AND ArtistID IN ($placeholders)
            ", $NewImportance, $tgroup->id(), ...$ArtistIDs
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
            $logger->group($tgroup, $Viewer, $change)
                ->general("$change in group {$tgroup->id()} ({$tgroup->name()}) by user " . $Viewer->label());
        }
    }
    $tgroup->refresh();
}
header("Location: {$tgroup->location()}");
