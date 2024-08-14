<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();
$notifier = new Gazelle\Notification\Torrent($Viewer->id());

switch ($_GET['action']) {
    case 'notify_catchup':
        $notifier->catchup();
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_catchup_filter':
        $filterId = (int)$_GET['filterid'];
        if (!$filterId) {
            error(0);
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
            error(0);
        }
        $notifier->clearFilter($filterId);
        header('Location: torrents.php?action=notify');
        break;

    case 'notify_clear_item':
        $torrentId = (int)$_GET['torrentid'];
        if (!$torrentId) {
            error(0);
        }
        $notifier->clearTorrentList([$torrentId]);
        break;

    case 'notify_clear_items':
        $cleared = $notifier->clearTorrentList(array_map(
            fn($n) => (int)$n, explode(',', $_GET['torrentids'] ?? '')
        ));
        if (!$cleared) {
            error(0);
        }
        break;

    default:
        error(0);
}
