<?php

$messageIds = array_filter(array_map('intval', $_POST['messages'] ?? []), fn($id) => $id > 0);

if (empty($messageIds)) {
    if (isset($_POST['unread'])) {
        $action = 'mark as unread';
    } elseif (isset($_POST['read'])) {
        $action = 'mark as read';
    } elseif (isset($_POST['pin'])) {
        $action = 'set (un)pinned';
    } else {
        $action = 'delete';
    }
    error("You forgot to select any messages to $action.");
}

$inbox = $Viewer->inbox();
$inbox->setFolder($_POST['section'] ?? 'inbox');

if (isset($_POST['delete'])) {
    $inbox->massRemove($messageIds);
} elseif (isset($_POST['unread'])) {
    $inbox->massUnread($messageIds);
} elseif (isset($_POST['read'])) {
    $inbox->massRead($messageIds);
} elseif (isset($_POST['pin'])) {
    $inbox->massTogglePinned($messageIds);
}

header("Location: " . $inbox->folderLink($inbox->folder(), $inbox->showUnreadFirst()));
