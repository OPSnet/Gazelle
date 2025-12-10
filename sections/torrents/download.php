<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\DownloadStatus;
use Gazelle\Util\Irc;

$torrent = new Manager\Torrent()->findById((int)($_REQUEST['id'] ?? 0));
if (is_null($torrent)) {
    json_or_error('could not find torrent', 404);
}

if (
    preg_match(
        BT_BROKEN_USERAGENT_REGEXP,
        $torrent->requestContext()->useragent(),
    )
    && $Viewer->torrentDownloadCount($torrent) > BT_BROKEN_USERAGENT_DOWNLOAD
) {
    json_or_error('You have downloaded this torrent file more than '
        . BT_BROKEN_USERAGENT_DOWNLOAD
        . 'times. If you need to download it again, please do so from a browser.'
    );
}

$download = new Download($torrent, new User\UserclassRateLimit($Viewer), isset($_REQUEST['usetoken']));
$status = $download->status();

if ($status == DownloadStatus::ok) {
    header('Content-Type: ' . ($Viewer->downloadAsText() ? 'text/plain' : 'application/x-bittorrent'));
    header('Content-Disposition: attachment; filename="' . $torrent->torrentFilename($Viewer->downloadAsText(), MAX_PATH_LEN) . '"');
    echo $torrent->torrentBody($Viewer->announceUrl());
    exit;
}

if ($status == DownloadStatus::flood) {
    $key = "ratelimit_flood_" . $Viewer->id;
    if ($Cache->get_value($key) === false) {
        $Cache->cache_value($key, true, 3600);
        Irc::sendMessage(
            IRC_CHAN_STATUS,
            "{$Viewer->publicLocation()} ({$Viewer->username()}) ({$Viewer->requestContext()->remoteAddr()}) accessing "
            . SITE_URL . $_SERVER['REQUEST_URI']
            . (!empty($_SERVER['HTTP_REFERER']) ? " from " . $_SERVER['HTTP_REFERER'] : '')
            . ' hit download rate limit'
        );
    }
}

json_or_error($status->message());
