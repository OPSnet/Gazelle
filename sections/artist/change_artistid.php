<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

$artMan = new Gazelle\Manager\Artist;
$artist = $artMan->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    error('Please select a valid artist to change.');
}

if (!empty($_POST['newartistid']) && !empty($_POST['newartistname'])) {
    error('Please enter a valid artist ID number or a valid artist name.');
}

$newId = (int)($_POST['newartistid'] ?? 0);
$newName = $_POST['newartistname'] ?? '';

if (empty($newName) && !$newId) {
    error('Please enter a valid artist ID number or a valid artist name.');
}

if ($newId) {
    // Make sure that's a real artist ID number, and grab the name
    $new = $artMan->findById($newId);
    if (is_null($new)) {
        error('Please enter a valid artist ID number.');
    }
} else {
    // Didn't give an ID, so try to grab based on the name
    $new = $artMan->findByName($newName);
    if (is_null($new)) {
        error('No artist by that name was found.');
    }
}

$ArtistID = $artist->id();
$ArtistName = $artist->name();
$NewArtistID = $new->id();
$NewArtistName = $new->name();

if ($ArtistID == $NewArtistID) {
    error('You cannot merge an artist with itself.');
}
if (isset($_POST['confirm'])) {
    // Get the information for the cache update
    $DB->prepared_query("
        SELECT DISTINCT GroupID
        FROM torrents_artists
        WHERE ArtistID = ?
        ", $ArtistID
    );
    $Groups = $DB->collect('GroupID');
    $DB->prepared_query("
        SELECT DISTINCT RequestID
        FROM requests_artists
        WHERE ArtistID = ?
        ", $ArtistID
    );
    $Requests = $DB->collect('RequestID');
    $DB->prepared_query("
        SELECT DISTINCT UserID
        FROM bookmarks_artists
        WHERE ArtistID = ?
        ", $ArtistID
    );
    $BookmarkUsers = $DB->collect('UserID');
    $DB->prepared_query("
        SELECT DISTINCT ct.CollageID
        FROM collages_torrents AS ct
        INNER JOIN torrents_artists AS ta USING (GroupID)
        WHERE ta.ArtistID = ?
        ", $ArtistID
    );
    $Collages = $DB->collect('CollageID');

    // And the info to avoid double-listing an artist if it and the target are on the same group
    $DB->prepared_query("
        SELECT DISTINCT GroupID
        FROM torrents_artists
        WHERE ArtistID = ?
        ", $NewArtistID
    );
    $NewArtistGroups = $DB->collect('GroupID');
    $NewArtistGroups[] = 0;

    $DB->prepared_query("
        SELECT DISTINCT RequestID
        FROM requests_artists
        WHERE ArtistID = ?
        ", $NewArtistID
    );
    $NewArtistRequests = $DB->collect('RequestID');
    $NewArtistRequests[] = 0;

    $DB->prepared_query("
        SELECT DISTINCT UserID
        FROM bookmarks_artists
        WHERE ArtistID = ?
        ", $NewArtistID
    );
    $NewArtistBookmarks = $DB->collect('UserID');
    $NewArtistBookmarks[] = 0;

    // Merge all of this artist's aliases onto the new artist
    $DB->prepared_query("
        UPDATE artists_alias SET
            ArtistID = ?
        WHERE ArtistID = ?
        ", $NewArtistID, $ArtistID
    );

    // Update the torrent groups, requests, and bookmarks
    $DB->prepared_query("
        UPDATE IGNORE torrents_artists SET
            ArtistID = ?
        WHERE ArtistID = ?
            AND GroupID NOT IN (" . placeholders($NewArtistGroups) . ")
        ", $NewArtistID, $ArtistID, ...$NewArtistGroups
    );
    $DB->prepared_query("
        DELETE FROM torrents_artists WHERE ArtistID = ?
        ", $ArtistID
    );
    $DB->prepared_query("
        UPDATE IGNORE requests_artists SET
            ArtistID = ?
        WHERE ArtistID = ?
            AND RequestID NOT IN (" . placeholders($NewArtistRequests) . ")
        ", $NewArtistID, $ArtistID, ...$NewArtistRequests
    );
    $DB->prepared_query("
        DELETE FROM requests_artists WHERE ArtistID = ?
        ", $ArtistID
    );
    $DB->prepared_query("
        UPDATE IGNORE bookmarks_artists SET
            ArtistID = ?
        WHERE ArtistID = ?
            AND UserID NOT IN (" . placeholders($NewArtistBookmarks) . ")
        ", $NewArtistID, $ArtistID, ...$NewArtistBookmarks
    );
    $DB->prepared_query("
        DELETE FROM bookmarks_artists WHERE ArtistID = ?
        ", $ArtistID
    );

    // Cache clearing
    if (!empty($Groups)) {
        $tgroupMan = new \Gazelle\Manager\TGroup;
        foreach ($Groups as $GroupID) {
            $tgroupMan->refresh($GroupID);
        }
    }
    if (!empty($Requests)) {
        foreach ($Requests as $RequestID) {
            $Cache->delete_value("request_artists_$RequestID");
            Requests::update_sphinx_requests($RequestID);
        }
    }
    if (!empty($BookmarkUsers)) {
        foreach ($BookmarkUsers as $UserID) {
            $Cache->delete_value("notify_artists_$UserID");
        }
    }
    if (!empty($Collages)) {
        foreach ($Collages as $CollageID) {
            $Cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $CollageID));
        }
    }

    $artist->flushCache();
    $new->flushCache();
    $Cache->delete_value("artist_groups_$ArtistID");
    $Cache->delete_value("artist_groups_$NewArtistID");

    // Delete the old artist
    $DB->prepared_query("
        DELETE FROM artists_group
        WHERE ArtistID = ?
        ", $ArtistID
    );
    (new Gazelle\Log)->general(
        "The artist $ArtistID ($ArtistName) was made into a non-redirecting alias of artist $NewArtistID ($NewArtistName) by user "
        . $Viewer->id() . " (" . $Viewer->username() . ')'
    );
    header("Location: artist.php?action=edit&artistid=$NewArtistID");
    exit;
}

echo $Twig->render('artist/merge.twig', [
    'artist'   => $artist,
    'new'      => $new,
    'viewer'   => $Viewer,
]);
