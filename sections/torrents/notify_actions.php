<?php
switch ($_GET['action']) {
    case 'notify_clear':
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = '0' AND UserID = ?
            ", $LoggedUser['ID']
        );
        $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_clear_item':
    case 'notify_clearitem':
        if (!isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) {
            error(0);
        }
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UserID = ? AND TorrentID = ?
            ", $LoggedUser[ID], $_GET[torrentid]
        );
        $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
        break;

    case 'notify_clear_items':
        if (!isset($_GET['torrentids'])) {
            error(0);
        }
        $TorrentIDs = explode(',', $_GET['torrentids']);
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UserID = ? AND TorrentID IN (" . placeholders($TorrentIDs) . ")
            ", $LoggedUser['ID'], ...$TorrentIDs
        );
        $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
        break;

    case 'notify_clear_filter':
    case 'notify_cleargroup':
        if (!isset($_GET['filterid']) || !is_number($_GET['filterid'])) {
            error(0);
        }
        $DB->prepared_query("
            DELETE FROM users_notify_torrents WHERE UnRead = '0' AND UserID = ? AND FilterID = ?
            ", $LoggedUser['ID'], (int)$_GET['filterid']
        );
        $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_catchup':
        $DB->prepared_query("
            UPDATE users_notify_torrents SET UnRead = '0' WHERE UserID = ?
            ", $LoggedUser['ID']
        );
        if ($DB->affected_rows()) {
            $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
        }
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_catchup_filter':
        if (!isset($_GET['filterid']) || !is_number($_GET['filterid'])) {
            error(0);
        }
        $DB->prepared_query("
            UPDATE users_notify_torrents SET UnRead='0' WHERE UserID = ? AND FilterID = ?
            ", $LoggedUser['ID'], $_GET['filterid']
        );
        if ($DB->affected_rows()) {
            $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
        }
        header('Location: torrents.php?action=notify');
        break;
    default:
        error(0);
}
