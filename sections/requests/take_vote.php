<?php

use \Gazelle\Manager\Notification;

//******************************************************************************//
//--------------- Vote on a request --------------------------------------------//
//This page is ajax!

if (!check_perms('site_vote')) {
    error(403);
}

authorize();

if (empty($_GET['id']) || !intval($_GET['id'])) {
    error(0);
}

$RequestID = $_GET['id'];

$DB->prepared_query('
    SELECT TorrentID
    FROM requests
    WHERE ID = ?', $RequestID);

if (!$DB->has_results()) {
    echo "missing";
    die();
}

list($FilledTorrentID) = $DB->next_record();
if ($FilledTorrentID > 0) {
    echo "filled";
    die();
}

$Amount = (empty($_GET['amount']) || !intval($_GET['amount']) || $_GET['amount'] < $MinimumVote)
    ? $MinimumVote
    : $_GET['amount'];

$Bounty = $Amount * (1 - $RequestTax);

if ($Viewer->uploadedSize() < $Amount) {
    echo 'bankrupt';
    die();
}

// Create vote!
$DB->prepared_query('
    INSERT INTO requests_votes
        (RequestID, UserID, Bounty)
    VALUES
        (?, ?, ?)
    ON DUPLICATE KEY UPDATE Bounty = Bounty + ?',
    $RequestID, $Viewer->id(), $Bounty, $Bounty);

$DB->prepared_query('
    UPDATE requests
    SET LastVote = NOW()
    WHERE ID = ?', $RequestID);

// Subtract amount from user
$DB->prepared_query('
    UPDATE users_leech_stats
    SET Uploaded = Uploaded - ?
    WHERE UserID = ?', $Amount, $Viewer->id());
$Cache->delete_value('user_stats_'.$Viewer->id());

Requests::update_sphinx_requests($RequestID);
$DB->prepared_query('
    SELECT UserID
    FROM requests_votes
    WHERE RequestID = ?
        AND UserID != ?', $RequestID, $Viewer->id());
$UserIDs = [];
while (list($UserID) = $DB->next_record()) {
    $UserIDs[] = $UserID;
}
$notification = new Notification;
$notification->notifyUsers($UserIDs, Notification::REQUESTALERTS, Format::get_size($Amount)
    . " of bounty has been added to a request you've voted on!", "requests.php?action=view&id=" . $RequestID);

$Cache->delete_value("request_$RequestID");
$Cache->delete_value("request_votes_$RequestID");

$ArtistForm = Requests::get_artists($RequestID);
foreach ($ArtistForm as $Importance) {
    foreach ($Importance as $Artist) {
        $Cache->delete_value('artists_requests_'.$Artist['id']);
    }
}

echo 'success';
