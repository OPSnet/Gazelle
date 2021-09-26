<?php

authorize();

$UserID = $Viewer->id();
$ConvID = (int)$_POST['convid'];
if (!$ConvID) {
    error(404);
}
if (!$DB->scalar("
    SELECT 1
    FROM pm_conversations_users
    WHERE UserID = ?
        AND ConvID = ?
    ", $UserID, $ConvID
)) {
    error(403);
}

if (isset($_POST['delete'])) {
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            InInbox   = '0',
            InSentbox = '0',
            Sticky    = '0'
        WHERE UserID = ?
            AND ConvID = ?
        ", $UserID, $ConvID
    );
} else {
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            Sticky = ?
        WHERE UserID = ?
            AND ConvID = ?
        ", isset($_POST['pin']) ? '1' : '0', $UserID, $ConvID
    );
    if (isset($_POST['mark_unread'])) {
        $DB->prepared_query("
            UPDATE pm_conversations_users SET
                Unread = '1'
            WHERE Unread = '0'
                AND UserID = ?
                AND ConvID = ?
            ", $UserID, $ConvID
        );
        if ($DB->affected_rows() > 0) {
            $Cache->increment('inbox_new_'.$UserID);
        }
    }
}
header("Location: inbox.php");
