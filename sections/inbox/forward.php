<?php

use Gazelle\Inbox;

authorize();

$UserID = $Viewer->id();
$ConvID = (int)$_POST['convid'];
$ReceiverID = (int)$_POST['receiverid'];
if (!$ConvID || !$ReceiverID) {
    error(404);
}
if (!$Viewer->permitted('users_mod') && !isset($StaffIDs[$ReceiverID])) {
    error(403);
}
$found = $DB->scalar("
    SELECT 1
    FROM pm_conversations_users
    WHERE InInbox = '1'
        AND ForwardedTo IN (0, UserID)
        AND UserID = ?
        AND ConvID = ?
    ", $UserID, $ConvID
);
if (!$found) {
    error(404);
}

$found = $DB->scalar("
    SELECT 1
    FROM pm_conversations_users
    WHERE InInbox = '1'
        AND ForwardedTo IN (0, UserID)
        AND UserID = ?
        AND ConvID = ?
    ", $ReceiverID, $ConvID
);
if ($found) {
    error("$StaffIDs[$ReceiverID] already has this conversation in their inbox.");
    header("Location: inbox.php?action=viewconv&id=$ConvID");
} else {
    $DB->prepared_query("
        INSERT IGNORE INTO pm_conversations_users
               (UserID, ConvID, InInbox, InSentbox, ReceivedDate)
        VALUES (?,      ?,      '1',     '0',       now())
        ON DUPLICATE KEY UPDATE
            ForwardedTo = 0,
            UnRead = 1
        ", $ReceiverID, $ConvID
    );
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            ForwardedTo = ?
        WHERE UserID = ?
            AND ConvID = ?
        ", $ReceiverID, $UserID, $ConvID
    );
    $Cache->delete_value("inbox_new_$ReceiverID");
    header('Location: ' . Inbox::getLinkQuick('inbox', $Viewer->option('ListUnreadPMsFirst') ?? false, Inbox::RAW));
}
