<?php

use Gazelle\Inbox;

authorize();

if (empty($_POST['toid'])) {
    error(404);
}

if (!empty($LoggedUser['DisablePM']) && !isset($StaffIDs[$_POST['toid']])) {
    error(403);
}

$ConvID = (int)($_POST['convid'] ?? 0);
if (!$ConvID) {
    // A new conversation
    $Subject = trim($_POST['subject']);
    if (empty($Subject)) {
        $Err = 'You cannot send a message without a subject.';
    }
} else {
    // A reply to an ongoing conversation
    $Subject = '';
    if (!$DB->scalar("
        SELECT UserID
        FROM pm_conversations_users
        WHERE UserID = ?
            AND ConvID = ?
            ", $Viewer->id(), $ConvID
    )) {
        error(403);
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
    $userMan = new Gazelle\Manager\User;
    if ($ConvID) {
        $userMan->replyPM($ToID, $Viewer->id(), $Subject, $Body, $ConvID);
    } else {
        $userMan->sendPM($ToID, $Viewer->id(), $Subject, $Body);
    }
    header('Location: ' . Inbox::getLinkQuick('inbox', $LoggedUser['ListUnreadPMsFirst'] ?? false, Inbox::RAW));
}
