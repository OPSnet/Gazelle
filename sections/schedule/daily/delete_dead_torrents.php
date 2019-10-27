<?php

//------------- Delete dead torrents ------------------------------------//

$DB->prepared_query("
    SELECT
        t.ID,
        t.GroupID,
        tg.Name,
        t.Format,
        t.Encoding,
        t.UserID,
        t.Media,
        HEX(t.info_hash) AS InfoHash
    FROM torrents AS t
    INNER JOIN torrents_leech_stats AS tls ON (tls.TorrentID = t.ID)
    INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    WHERE
        (tls.last_action IS NOT NULL AND tls.last_action < now() - INTERVAL 28 DAY)
        OR
        (tls.last_action IS NULL AND t.Time < now() - INTERVAL 2 DAY)
    LIMIT 8000
");
$torrents = $DB->to_array(false, MYSQLI_NUM, false);
echo('Found '.count($torrents)." inactive torrents to be deleted.\n");

$logEntries = $deleteNotes = [];

// Exceptions for inactivity deletion
$inactivityExceptionsMade = [
    //UserID => expiry time of exception
];
$i = 0;
foreach ($torrents as $torrent) {
    list($id, $groupID, $name, $format, $encoding, $userID, $media, $infoHash) = $torrent;
    if (array_key_exists($userID, $inactivityExceptionsMade) && (time() < $inactivityExceptionsMade[$userID])) {
        // don't delete the torrent!
        continue;
    }
    $artistName = Artists::display_artists(Artists::get_artist($groupID), false, false, false);
    if ($artistName) {
        $name = "$artistName - $name";
    }
    if ($format && $encoding) {
        $name .= ' ['.(empty($media) ? '' : "$media / ") . "$format / $encoding]";
    }
    Torrents::delete_torrent($id, $groupID);
    $logEntries[] = "Torrent $id ($name) (".strtoupper($infoHash).") was deleted for inactivity (unseeded)";

    if (!array_key_exists($userID, $deleteNotes)) {
        $deleteNotes[$userID] = ['Count' => 0, 'Msg' => ''];
    }

    $deleteNotes[$userID]['Msg'] .= sprintf("\n[url=torrents.php?id=%s]%s[/url]", $groupID, $name);
    $deleteNotes[$userID]['Count']++;

    ++$i;
    if ($i % 500 == 0) {
        echo("$i inactive torrents removed.\n");
    }
}
echo("$i torrents deleted for inactivity.\n");

foreach ($deleteNotes as $userID => $messageInfo) {
    $singular = (($messageInfo['Count'] == 1) ? true : false);
    Misc::send_pm($userID, 0, $messageInfo['Count'].' of your torrents '.($singular ? 'has' : 'have').' been deleted for inactivity', ($singular ? 'One' : 'Some').' of your uploads '.($singular ? 'has' : 'have').' been deleted for being unseeded. Since '.($singular ? 'it' : 'they').' didn\'t break any rules (we hope), please feel free to re-upload '.($singular ? 'it' : 'them').".\n\nThe following torrent".($singular ? ' was' : 's were').' deleted:'.$messageInfo['Msg']);
}
unset($deleteNotes);

if (count($logEntries) > 0) {
    $chunks = array_chunk($logEntries, 100);
    foreach ($chunks as $messages) {
        $placeholders = array_fill(0, count($messages), '(?, now())');
        $DB->prepared_query("
                INSERT INTO log (Message, Time)
                VALUES " . implode(', ', $placeholders), ...$messages);
        echo("\nDeleted $i torrents for inactivity\n");
    }
}

$DB->prepared_query("
        SELECT SimilarID
        FROM artists_similar_scores
        WHERE Score <= 0");
$similarIDs = $DB->collect('SimilarID');

if ($similarIDs) {
    $placeholders = implode(', ', array_fill(0, count($similarIDs), '(?)'));
    $DB->prepared_query("
            DELETE FROM artists_similar
            WHERE SimilarID IN ($placeholders)",
            $similarIDs);
    $DB->prepared_query("
            DELETE FROM artists_similar_scores
            WHERE SimilarID IN ($placeholders)",
            $similarIDs);
    $DB->prepared_query("
            DELETE FROM artists_similar_votes
            WHERE SimilarID IN ($placeholders)",
            $similarIDs);
}
