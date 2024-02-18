<?php

use Gazelle\Enum\DownloadStatus;
use Gazelle\Util\Irc;

$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_REQUEST['id'] ?? 0));
if (is_null($torrent)) {
    json_or_error('could not find torrent', 404);
}
$torrent->setViewer($Viewer);

/* uTorrent Remote and various scripts redownload .torrent files periodically.
 * To prevent this retardation from blowing bandwidth etc., let's block it
 * if the .torrent file has been downloaded four times before.
 */
if (preg_match('/^(BTWebClient|Python-urllib|python-requests|uTorrent)/', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
    && $Viewer->torrentDownloadCount($torrent->id()) > 3
) {
    json_or_error('You have already downloaded this torrent file four times. If you need to download it again, please do so from your browser.');
}

$download = new Gazelle\Download($torrent, new Gazelle\User\UserclassRateLimit($Viewer), isset($_REQUEST['usetoken']));
$status = $download->status();

if ($status == DownloadStatus::ok) {
    header('Content-Type: ' . ($Viewer->downloadAsText() ? 'text/plain' : 'application/x-bittorrent') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $torrent->torrentFilename($Viewer->downloadAsText(), MAX_PATH_LEN) . '"');
    echo $torrent->torrentBody($Viewer->announceUrl());
    exit;
}

if ($status == DownloadStatus::flood) {
    $key = "ratelimit_flood_" . $Viewer->id();
    if ($Cache->get_value($key) === false) {
        $Cache->cache_value($key, true, 3600);
        Irc::sendMessage(
            IRC_CHAN_STATUS,
            "{$Viewer->publicLocation()} ({$Viewer->username()}) ({$_SERVER['REMOTE_ADDR']}) accessing "
            . SITE_URL . $_SERVER['REQUEST_URI']
            . (!empty($_SERVER['HTTP_REFERER']) ? " from " . $_SERVER['HTTP_REFERER'] : '')
            . ' hit download rate limit'
        );
    }
}

json_or_error($status->message());
