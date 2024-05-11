<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

if (empty($_POST['importance']) || empty($_POST['artists']) || empty($_POST['groupid']) || !is_number($_POST['importance']) || !is_number($_POST['groupid'])) {
    error(0);
}

$tgroup = (new Gazelle\Manager\TGroup())->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}
$Artists = explode(',', $_POST['artists']);

$CleanArtists = [];
$AliasIDs = [];
$ArtistsString = '0';

foreach ($Artists as $Artist) {
    [$Importance, $AliasID] = explode(';', $Artist);
    if ($AliasID && $Importance) {
        $CleanArtists[] = [(int)$Importance, (int)$AliasID];
        $AliasIDs[] = (int)$AliasID;
    }
}

if (count($CleanArtists) > 0) {
    $db = Gazelle\DB::DB();
    $logger = new Gazelle\Log();
    $placeholders = placeholders($AliasIDs);
    if ($_POST['manager_action'] == 'delete') {
        $artistMan = new Gazelle\Manager\Artist();
        $changedArtists = [];
        foreach ($CleanArtists as $Artist) {
            [$Importance, $AliasID] = $Artist;
            $db->prepared_query("
                DELETE FROM torrents_artists
                WHERE GroupID = ?
                    AND AliasID = ?
                    AND Importance = ?
                ", $tgroup->id(), $AliasID, $Importance
            );
            if ($db->affected_rows()) {
                $artist = $artistMan->findByAliasId($AliasID);
                $changedArtists[$artist->id()] = $artist;
                $change = "artist {$artist->id()} ({$artist->name()}) removed as " . ARTIST_TYPE[$Importance];
                $logger->group($tgroup, $Viewer, $change)
                    ->general("$change in group {$tgroup->id()} ({$tgroup->name()}) by user {$Viewer->label()}");
            }
        }
        foreach ($changedArtists as $artist) {
            if (!$artist->usageTotal()) {
                $artist->remove($Viewer, $logger);
            }
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
                AND AliasID IN ($placeholders)
            ", $NewImportance, $tgroup->id(), ...$AliasIDs
        );
        $artistMan = new Gazelle\Manager\Artist();
        foreach ($CleanArtists as $Artist) {
            [$Importance, $AliasID] = $Artist;
            // Don't bother logging artists whose importance hasn't changed
            if ($Importance === $NewImportance) {
                continue;
            }
            $artist = $artistMan->findByAliasId($AliasID);
            $change = "artist {$artist->id()} ({$artist->name()}) changed role from "
                . ARTIST_TYPE[$Importance] . " to " . ARTIST_TYPE[$NewImportance];
            $logger->group($tgroup, $Viewer, $change)
                ->general("$change in group {$tgroup->id()} ({$tgroup->name()}) by user " . $Viewer->label());
        }
    }
    $tgroup->refresh();
}
header("Location: {$tgroup->location()}");
