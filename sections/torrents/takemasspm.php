<?php
//******************************************************************************//
//--------------- Take mass PM -------------------------------------------------//
// This pages handles the backend of the 'Send Mass PM' function. It checks     //
// the data, and if it all validates, it sends a PM to everyone who snatched    //
// the torrent.                                                                 //
//******************************************************************************//

authorize();

enforce_login();

$Validate = new Validate;

$TorrentID = (int)$_POST['torrentid'];
$GroupID = (int)$_POST['groupid'];
$Subject = $_POST['subject'];
$Message = $_POST['message'];

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

// FIXME: Still need a better perm name
if (!check_perms('site_moderate_requests')) {
    error(403);
}

$Validate->SetFields('torrentid', '1', 'number', 'Invalid torrent ID.', ['minlength' => 1]);
$Validate->SetFields('groupid', '1', 'number', 'Invalid group ID.', [ 'minlength' => 1]);
$Validate->SetFields('subject', '0', 'string', 'Invalid subject.', ['maxlength' => 1000, 'minlength' => 1]);
$Validate->SetFields('message', '0', 'string', 'Invalid message.', ['maxlength' => 10000, 'minlength' => 1]);
$Err = $Validate->ValidateForm($_POST); // Validate the form

if ($Err) {
    error($Err);
    $Location = (empty($_SERVER['HTTP_REFERER'])) ? "torrents.php?action=masspm&amp;id={$GroupID}&amp;torrentid={$TorrentID}" : $_SERVER['HTTP_REFERER'];
    header("Location: {$Location}");
    die();
}

//******************************************************************************//
//--------------- Send PMs to users --------------------------------------------//

$DB->prepared_query('
    SELECT uid
    FROM xbt_snatched
    WHERE fid = ?
    ', $TorrentID
);

$Snatchers = $DB->to_array();
foreach ($Snatchers as $UserID) {
    Misc::send_pm($UserID[0], 0, $Subject, $Message);
}

Misc::write_log($LoggedUser['Username']." sent mass notice to snatchers of torrent $TorrentID in group $GroupID");
header("Location: torrents.php?id=$GroupID");
