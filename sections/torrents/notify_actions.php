<?php
switch ($_GET['action']) {
    case 'notify_clear':
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = 0 AND UserID = ?
            ", $Viewer->id()
        );
        $Cache->delete_value('user_notify_upload_'.$Viewer->id());
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_clear_item':
    case 'notify_clearitem':
        if (!isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) {
            error(0);
        }
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UserID = ? AND TorrentID = ?
            ", $Viewer->id(), $_GET['torrentid']
        );
        $Cache->delete_value('user_notify_upload_'.$Viewer->id());
        break;

    case 'notify_clear_items':
        if (!isset($_GET['torrentids'])) {
            error(0);
        }
        $TorrentIDs = explode(',', $_GET['torrentids']);
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UserID = ? AND TorrentID IN (" . placeholders($TorrentIDs) . ")
            ", $Viewer->id(), ...$TorrentIDs
        );
        $Cache->delete_value('user_notify_upload_'.$Viewer->id());
        break;

    case 'notify_clear_filter':
    case 'notify_cleargroup':
        if (!isset($_GET['filterid']) || !is_number($_GET['filterid'])) {
            error(0);
        }
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = 0 AND UserID = ? AND FilterID = ?
            ", $Viewer->id(), (int)$_GET['filterid']
        );
        $Cache->delete_value('user_notify_upload_'.$Viewer->id());
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_catchup':
        $DB->prepared_query("
            UPDATE users_notify_torrents SET UnRead = 0 WHERE UserID = ?
            ", $Viewer->id()
        );
        if ($DB->affected_rows()) {
            $Cache->delete_value('user_notify_upload_'.$Viewer->id());
        }
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_catchup_filter':
        if (!isset($_GET['filterid']) || !is_number($_GET['filterid'])) {
            error(0);
        }
        $DB->prepared_query("
            UPDATE users_notify_torrents SET UnRead='0' WHERE UserID = ? AND FilterID = ?
            ", $Viewer->id(), $_GET['filterid']
        );
        if ($DB->affected_rows()) {
            $Cache->delete_value('user_notify_upload_'.$Viewer->id());
        }
        header('Location: torrents.php?action=notify');
        break;
    default:
        error(0);
}
