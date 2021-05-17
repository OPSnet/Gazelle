<?php
//******************************************************************************//
//--------------- Take unfill request ------------------------------------------//

authorize();

$RequestID = $_POST['id'];
if (!intval($RequestID)) {
    error(0);
}

$DB->prepared_query('
    SELECT
        r.CategoryID,
        r.UserID,
        r.FillerID,
        r.Title,
        uls.Uploaded,
        r.GroupID
    FROM requests AS r
    LEFT JOIN users_leech_stats AS uls ON (uls.UserID = r.FillerID)
    WHERE r.ID = ?', $RequestID);
list($CategoryID, $UserID, $FillerID, $Title, $Uploaded, $GroupID) = $DB->next_record();

if (((intval($LoggedUser['ID']) !== $UserID && intval($LoggedUser['ID']) !== $FillerID) && !check_perms('site_moderate_requests')) || $FillerID === '0') {
    error(403);
}

// Unfill
$DB->prepared_query('
    UPDATE requests
    SET TorrentID = 0,
        FillerID = 0,
        TimeFilled = null,
        Visible = 1
    WHERE ID = ?', $RequestID);

$CategoryName = $Categories[$CategoryID - 1];

$ArtistForm = Requests::get_artists($RequestID);
$ArtistName = Artists::display_artists($ArtistForm, false, true);
$FullName = $ArtistName.$Title;

$RequestVotes = Requests::get_votes_array($RequestID);

if ($RequestVotes['TotalBounty'] > $Uploaded) {
    // If we can't take it all out of upload, zero that out and add whatever is left as download.
    $DB->prepared_query('
        UPDATE users_leech_stats
        SET Uploaded = 0, Downloaded = Downloaded + ?
        WHERE UserID = ?',
        $RequestVotes['TotalBounty'] - $Uploaded, $FillerID);
} else {
    $DB->prepared_query('
        UPDATE users_leech_stats
        SET Uploaded = Uploaded - ?
        WHERE UserID = ?', $RequestVotes['TotalBounty'], $FillerID);
}

$userMan = new Gazelle\Manager\User;
$userMan->sendPM($FillerID, 0,
    'A request you filled has been unfilled',
    "The request \"[url=requests.php?action=view&amp;id=$RequestID]$FullName"
        . "[/url]\" was unfilled by [url=user.php?id={$LoggedUser['ID']}]"
        . "{$LoggedUser['Username']}[/url] for the reason: [quote]{$_POST['reason']}"
        . "[/quote]\nIf you feel like this request was unjustly unfilled, please [url="
        . "reports.php?action=report&amp;type=request&amp;id={$RequestID}]report the request[/url] and explain why this request should not have been unfilled."
);

$Cache->delete_value("user_stats_$FillerID");

if ($UserID !== $LoggedUser['ID']) {
    $userMan->sendPM($UserID, 0,
        'A request you created has been unfilled',
        "The request \"[url=requests.php?action=view&amp;id=$RequestID]$FullName"
            . "[/url]\" was unfilled by [url=user.php?id={$LoggedUser['ID']}]"
            . $LoggedUser['Username']."[/url] for the reason: [quote]".$_POST['reason'].'[/quote]'
    );
}

(new Gazelle\Log)->general("Request $RequestID ($FullName), with a "
    . Format::get_size($RequestVotes['TotalBounty']).' bounty, was unfilled by user '
    . $LoggedUser['ID'].' ('.$LoggedUser['Username'].') for the reason: '.$_POST['reason']
);

$Cache->delete_value("request_$RequestID");
$Cache->delete_value("request_artists_$RequestID");
if ($GroupID) {
    $Cache->delete_value("requests_group_$GroupID");
}

Requests::update_sphinx_requests($RequestID);

if (!empty($ArtistForm)) {
    foreach ($ArtistForm as $ArtistType) {
        foreach ($ArtistType as $Artist) {
            $Cache->delete_value('artists_requests_'.$Artist['id']);
        }
    }
}

$SphQL = new SphinxqlQuery();
$SphQL->raw_query("
        UPDATE requests, requests_delta
        SET torrentid = 0, fillerid = 0
        WHERE id = $RequestID", false);

header("Location: requests.php?action=view&id=$RequestID");
