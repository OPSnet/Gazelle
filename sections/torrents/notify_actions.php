<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();
$notifier = new Notification\Torrent($Viewer);

switch ($_GET['action']) {
    case 'notify_catchup':
        $notifier->catchup();
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_catchup_filter':
        $filterId = (int)$_GET['filterid'];
        if (!$filterId) {
            Error404::error('Notification filter not found for catch up');
        }
        $notifier->catchupFilter($filterId);
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_clear':
        $notifier->clearRead();
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_clear_filter':
        $filterId = (int)$_GET['filterid'];
        if (!$filterId) {
            Error404::error('Notification filter not found for clear');
        }
        $notifier->clearFilter($filterId);
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_clear_item':
        $torrentId = (int)$_GET['torrentid'];
        if (!$torrentId) {
            Error404::error('Torrent id not found for clear');
        }
        $notifier->clearTorrentList([$torrentId]);
        break;

    case 'notify_clear_items':
        $cleared = $notifier->clearTorrentList(array_map(
            fn($n) => (int)$n, explode(',', $_GET['torrentids'] ?? '')
        ));
        if (!$cleared) {
            Error400::error('Unable to clear marked torrents');
        }
        break;

    default:
        Error400::error('Unknown notification action');
}
