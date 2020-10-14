<?php
$UserID = $LoggedUser['ID'];
$SimilarID = (int)$_GET['similarid'];
$ArtistID = (int)$_GET['artistid'];
$Way = trim($_GET['way']);

if (!$SimilarID || !$ArtistID || !in_array($Way, ['up', 'down'])) {
    error(404);
}

$found = $DB->scalar("
    SELECT 1
    FROM artists_similar_votes
    WHERE SimilarID = ?
        AND UserID = ?
        AND Way = ?
    ", $SimilarID, $UserID, $Way
);
if (!$found) {
    $change = 0;
    if ($Way == 'down') {
        $change = -100;
    } elseif ($Way == 'up') {
        $change = 100;
    }
    $DB->prepared_query("
        UPDATE artists_similar_scores SET
            Score = Score + ?
        WHERE SimilarID = ?
        ", $change, $SimilarID
    );
    $DB->prepared_query("
        INSERT INTO artists_similar_votes
               (SimilarID, UserID, Way)
        VALUES (?,         ?,      ?)
        ", $SimilarID, $UserID, $Way
    );
    $Cache->delete_value('artist_'.$ArtistID);
}
header("Location: " . redirectUrl("artist.php?id={$ArtistID}"));
