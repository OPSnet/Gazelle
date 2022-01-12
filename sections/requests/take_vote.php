<?php

use \Gazelle\Manager\Notification;

//******************************************************************************//
//--------------- Vote on a request --------------------------------------------//
//This page is ajax!

if (!$Viewer->permitted('site_vote')) {
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

$Amount = (empty($_GET['amount']) || !intval($_GET['amount']) || $_GET['amount'] < REQUEST_MIN)
    ? REQUEST_MIN
    : $_GET['amount'];

$Bounty = $Amount * (1 - REQUEST_TAX);

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

$Cache->delete_value("request_$RequestID");
$Cache->delete_value("request_votes_$RequestID");

$ArtistForm = Requests::get_artists($RequestID);
foreach ($ArtistForm as $Importance) {
    foreach ($Importance as $Artist) {
        $Cache->delete_value('artists_requests_'.$Artist['id']);
    }
}

echo 'success';
