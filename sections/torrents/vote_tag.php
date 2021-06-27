<?php
$UserID = $Viewer->id();
$TagID = $_GET['tagid'];
$GroupID = $_GET['groupid'];
$Way = $_GET['way'];

if (!is_number($TagID) || !is_number($GroupID)) {
    error(404);
}
if (!in_array($Way, ['up', 'down'])) {
    error(404);
}

$DB->prepared_query("
    SELECT TagID
    FROM torrents_tags_votes
    WHERE TagID = ?
        AND GroupID = ?
        AND UserID = ?
        AND Way = ?
    ", $TagID, $GroupID, $UserID, $Way
);
if (!$DB->has_results()) {
    if ($Way == 'down') {
        $Change = 'NegativeVotes = NegativeVotes + 1';
    } else {
        $Change = 'PositiveVotes = PositiveVotes + 2';
    }
    $DB->prepared_query("
        UPDATE torrents_tags SET
            $Change
        WHERE TagID = ?
            AND GroupID = ?
        ", $TagID, $GroupID
    );
    $DB->prepared_query("
        INSERT INTO torrents_tags_votes
               (GroupID, TagID, UserID, Way)
        VALUES (?,       ?,     ?,      ?)
        ", $GroupID, $TagID, $UserID, $Way
    );
    $Cache->delete_value("torrents_details_$GroupID");
}

header("Location: " . $_SERVER['HTTP_REFERER'] ?? "torrents.php?id=$GroupID");
