<?php

if (empty($_GET['userid'])) {
    $UserID = $Viewer->id();
} else {
    if (!check_perms('users_override_paranoia')) {
        print json_encode(['status' => 'failure']);
        die();
    }
    $UserID = $_GET['userid'];
    $Sneaky = ($UserID != $LoggedUser['ID']);
    if (!is_number($UserID)) {
        print json_encode(['status' => 'failure']);
        die();
    }
    $Username = $DB->scalar("
        SELECT Username
        FROM users_main
        WHERE ID = ?
        ", $UserID
    );
}

$Sneaky = ($UserID != $LoggedUser['ID']);

$DB->prepared_query("
    SELECT ag.ArtistID, ag.Name
    FROM bookmarks_artists AS ba
    INNER JOIN artists_group AS ag USING (ArtistID)
    WHERE ba.UserID = ?
    ", $UserID
);
$ArtistList = $DB->to_array();

$JsonArtists = [];
foreach ($ArtistList as $Artist) {
    list($ArtistID, $Name) = $Artist;
    $JsonArtists[] = [
        'artistId' => (int)$ArtistID,
        'artistName' => $Name
    ];
}

print json_encode([
    'status' => 'success',
    'response' => [
        'artists' => $JsonArtists
    ]
]);
