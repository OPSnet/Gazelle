<?php

use Gazelle\Inbox;

enforce_login();
$UserID = $Viewer->id();
$Section = $_POST['section'];
$UnreadFirst = (bool) $_POST['sort'];

if (!isset($_POST['messages']) || !is_array($_POST['messages'])) {
    $Message = 'to delete';
    if (isset($_POST['unread'])) {
        $Message = 'to mark as unread';
    }
    elseif (isset($_POST['read'])) {
        $Message = 'to mark as read';
    }
    elseif (isset($_POST['sticky'])) {
        $Message = 'to mark as (un)sticky';
    }
    error("You forgot to select any messages $Message.");
    header('Location: ' . Inbox::getLinkQuick($Section, $UnreadFirst, Inbox::RAW));
    die();
}

$Messages = $_POST['messages'];
foreach ($Messages AS $ConvID) {
    if ((int)$ConvID < 1) {
        error(0);
    }
}
$placeholders = placeholders($Messages);
$args = array_merge($Messages, [$UserID]);
$MessageCount = $DB->scalar("
    SELECT count(*)
    FROM pm_conversations_users
    WHERE ConvID IN ($placeholders) AND UserID = ?
    ", ...$args
);
if ($MessageCount != count($Messages)) {
    error(0);
}

if (isset($_POST['delete'])) {
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            InInbox   = '0',
            InSentbox = '0',
            Sticky    = '0',
            UnRead    = '0'
        WHERE ConvID IN ($placeholders) AND UserID = ?
        ", ...$args
    );
} elseif (isset($_POST['unread'])) {
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            Unread = '1'
        WHERE ConvID IN ($placeholders) AND UserID = ?
        ", ...$args
    );
} elseif (isset($_POST['read'])) {
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            Unread = '0'
        WHERE ConvID IN ($placeholders) AND UserID = ?
        ", ...$args
    );
} elseif (isset($_POST['sticky'])) {
    $DB->prepared_query("
        UPDATE pm_conversations_users SET
            Sticky = CASE WHEN Sticky = '0' THEN '1' ELSE '0' END
        WHERE ConvID IN ($placeholders) AND UserID = ?
        ", ...$args
    );
}

$Cache->delete_value('inbox_new_'.$UserID);

header('Location: ' . Inbox::getLinkQuick($Section, $UnreadFirst, Inbox::RAW));
