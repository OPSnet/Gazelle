<?php

use Gazelle\Inbox;

authorize();

if (empty($_POST['toid'])) {
    error(404);
}

if (!empty($LoggedUser['DisablePM']) && !isset($StaffIDs[$_POST['toid']])) {
    error(403);
}

if (($ConvID = (int)$_POST['convid']) > 0) {
    // A reply to an ongoing conversation
    $Subject = '';
    if (!$DB->scalar("
        SELECT UserID
        FROM pm_conversations_users
        WHERE UserID = ?
            AND ConvID = ?
            ", $LoggedUser['ID'], $ConvID
    )) {
        error(403);
    }
} else {
    // A new conversation
    $ConvID = null;
    $Subject = trim($_POST['subject']);
    if (empty($Subject)) {
        $Err = 'You cannot send a message without a subject.';
    }
}
$ToID = $DB->scalar("
    SELECT ID
    FROM users_main
    WHERE ID = ?
    ", (int)$_POST['toid']
);
if (!$ToID) {
    $Err = 'Recipient does not exist.';
}

$Body = trim($_POST['body']);
if ($Body === '' || $Body === false) {
    $Err = 'You cannot send a message without a body.';
}

if (!empty($Err)) {
    error($Err);
    $Return = true;
    require(__DIR__ . '/compose.php');
} else {
    Misc::send_pm($ToID, $LoggedUser['ID'], $Subject, $Body, $ConvID);
    header('Location: ' . Inbox::getLinkQuick('inbox', $LoggedUser['ListUnreadPMsFirst'] ?? false, Inbox::RAW));
}
