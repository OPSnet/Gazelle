<?php
authorize();

$UserID = $LoggedUser['ID'];
$Artist1ID = (int)$_POST['artistid'];
$Artist2Name = trim($_POST['artistname']);

if (!$Artist1ID) {
    error(404);
}

if (empty($Artist2Name)) {
    error('Blank artist name.');
}

$Artist2ID = $DB->scalar("
    SELECT ArtistID
    FROM artists_group
    WHERE Name = ?
    ", $Artist2Name
);
if (!empty($Artist2ID)) { // artist was found in the database

    // Let's see if there's already a similar artists field for these two
    $SimilarID = $DB->scalar("
        SELECT s1.SimilarID
        FROM artists_similar AS s1
            JOIN artists_similar AS s2 ON s1.SimilarID = s2.SimilarID
        WHERE s1.ArtistID = ?
            AND s2.ArtistID = ?
        ", $Artist1ID, $Artist2ID
    );
    if ($SimilarID) { // The similar artists field already exists, just update the score
        $DB->prepared_query("
            UPDATE artists_similar_scores SET
                Score = Score + 200
            WHERE SimilarID = ?
            ", $SimilarID
        );
    } else { // No, it doesn't exist - create it
        $DB->prepared_query("
            INSERT INTO artists_similar_scores (Score) VALUES (200)
        ");
        $SimilarID = $DB->inserted_id();
        $DB->prepared_query("
            INSERT INTO artists_similar
                   (ArtistID, SimilarID)
            VALUES (?, ?), (?, ?)
            ", $Artist1ID, $SimilarID, $Artist2ID, $SimilarID
        );
    }

    $DB->prepared_query("
        SELECT SimilarID
        FROM artists_similar_votes
        WHERE Way = 'up'
            AND UserID = ?
            AND SimilarID = ?
        ", $UserID, $SimilarID
    );
    if (!$DB->has_results()) {
        $DB->prepared_query("
            INSERT INTO artists_similar_votes
                   (SimilarID, UserID, way)
            VALUES (?,         ?,      'up')
            ", $SimilarID, $UserID
        );
    }

    $artist = new \Gazelle\Artist($Artist1ID);
    $artist->flushCache();
    $artist = new \Gazelle\Artist($Artist2ID);
    $artist->flushCache();
    $Cache->deleteMulti(["similar_positions_$Artist1ID", "similar_positions_$Artist2ID"]);
}

header("Location: " . redirectUrl("artist.php?id={$ArtistID}"));
