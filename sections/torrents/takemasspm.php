<?php
//******************************************************************************//
//--------------- Take mass PM -------------------------------------------------//
// This pages handles the backend of the 'Send Mass PM' function. It checks     //
// the data, and if it all validates, it sends a PM to everyone who snatched    //
// the torrent.                                                                 //
//******************************************************************************//

authorize();
enforce_login();

$TorrentID = (int)$_POST['torrentid'];
$GroupID = (int)$_POST['groupid'];
$Subject = $_POST['subject'];
$Message = $_POST['message'];

// FIXME: Still need a better perm name
if (!check_perms('site_moderate_requests')) {
    error(403);
}

$Validate = new Gazelle\Util\Validator;
$Validate->setFields([
    ['torrentid', '1', 'number', 'Invalid torrent ID.', ['range' => [1, 999999999]]],
    ['groupid', '1', 'number', 'Invalid group ID.', [ 'range' => [1, 999999999]]],
    ['subject', '0', 'string', 'Invalid subject.', ['maxlength' => 1000]],
    ['message', '0', 'string', 'Invalid message.', ['maxlength' => 10000]],
]);
if (!$Val->validate($_POST)) {
    error($Val->errorMessage());
}

$DB->prepared_query('
    SELECT uid FROM xbt_snatched WHERE fid = ?
    ', $TorrentID
);

$Snatchers = $DB->to_array();
foreach ($Snatchers as $UserID) {
    Misc::send_pm($UserID[0], 0, $Subject, $Message);
}
$n = count($Snatchers);
(new Gazelle\Log)->general($LoggedUser['Username']." sent a mass PM to $n snatcher" . plural($n) . " of torrent $TorrentID in group $GroupID");

header("Location: torrents.php?id=$GroupID");
