<?php

if (empty($_GET['id']) || !is_number($_GET['id']) || empty($_GET['limit']) || !is_number($_GET['limit'])) {
    print json_die('failure');
}

$artistId = $_GET["id"];
$limit = $_GET["limit"];

$db = Gazelle\DB::DB();
$db->prepared_query("
    SELECT
        s2.ArtistID,
        ag.Name,
        ass.Score
    FROM artists_similar AS s1
    INNER JOIN artists_similar AS s2 ON (s1.SimilarID = s2.SimilarID AND s1.ArtistID != s2.ArtistID)
    INNER JOIN artists_similar_scores AS ass ON (ass.SimilarID = s1.SimilarID)
    INNER JOIN artists_group AS ag ON (ag.ArtistID = s2.ArtistID)
    WHERE ass.Score >= 0
        AND s1.ArtistID = ?
    ORDER BY ass.Score DESC
    LIMIT ?
    ", $artistId, $limit
);

$results = [];
while ([$ArtistID, $Name, $Score] = $db->next_record(MYSQLI_NUM, false)) {
    $results[] = [
        'id'    => (int)$ArtistID,
        'name'  => $Name,
        'score' => (int)$Score,
    ];
}

print json_encode($results);
