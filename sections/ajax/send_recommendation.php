<?php
$FriendID = (int)$_POST['friend'];
$Type = $_POST['type'];
$ID = (int)$_POST['id'];
$Note = $_POST['note'];

if (empty($FriendID) || empty($Type) || empty($ID)) {
    echo json_die(['status' => 'error', 'response' => 'missing parameters']);
}
// Make sure the recipient is on your friends list and not some random dude.
$DB->prepared_query("
    SELECT f.FriendID, u.Username
    FROM friends AS f
    RIGHT JOIN users_enable_recommendations AS r ON (r.ID = f.FriendID AND r.Enable = 1)
    RIGHT JOIN users_main AS u ON (u.ID = f.FriendID)
    WHERE f.UserID = ?
        AND f.FriendID = ?
    ", $LoggedUser['ID'], $FriendID
);
if (!$DB->has_results()) {
    echo json_die(['status' => 'error', 'response' => 'not on friend list']);
}

$Type = strtolower($Type);
switch ($Type) {
    case 'torrent':
        $Article = 'a';
        $Link = "torrents.php?id=$ID";
        $Name = $DB->scalar("
            SELECT Name FROM torrents_group WHERE ID = ?
            ", $ID);
        break;
    case 'artist':
        $Article = 'an';
        $Link = "artist.php?id=$ID";
        $Name = $DB->scalar("
            SELECT Name FROM artists_group WHERE ArtistID = ?
            ", $ID);
        break;
    case 'collage':
        $Article = 'a';
        $Link = "collages.php?id=$ID";
        $Name = $DB->scalar("
            SELECT Name FROM collages WHERE ID = ?
            ", $ID);
        break;
    default:
        echo json_die(['status' => 'error', 'response' => 'bad parameters']);
        break;
}

$Body = $LoggedUser['Username'] . " recommended you the $Type [url=".site_url()."$Link]$Name".'[/url].';
if (!empty($Note)) {
    $Body = "$Body\n\n$Note";
}

Misc::send_pm($FriendID, $LoggedUser['ID'], $LoggedUser['Username'] . " recommended you $Article $Type!", $Body);
echo json_encode(['status' => 'success', 'response' => 'Sent!']);
