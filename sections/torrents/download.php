<?php

use \Gazelle\Util\Irc;

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_REQUEST['id'] ?? 0));
if (!$torrent) {
    json_or_error('could not find torrent', 404);
}
$torrent->setViewer($Viewer);
$torrentId = $torrent->id();

/* uTorrent Remote and various scripts redownload .torrent files periodically.
 * To prevent this retardation from blowing bandwidth etc., let's block it
 * if the .torrent file has been downloaded four times before.
 */
if (preg_match('/^(BTWebClient|Python-urllib|python-requests|uTorrent)/', $_SERVER['HTTP_USER_AGENT'])
    && $Viewer->torrentDownloadCount($torrentId) > 3
) {
    $Msg = 'You have already downloaded this torrent file four times. If you need to download it again, please do so from your browser.';
    json_or_error($Msg, $Msg, true);
}

/* If this is not their torrent, then see if they have downloaded too
 * many files, compared to completely snatched items. If that is too
 * high, and they have already downloaded too many files recently, then
 * stop them. Exception: always allowed if they are using FL tokens.
 */
$userId = $Viewer->id();
if (!($_REQUEST['usetoken'] ?? 0) && $torrent->uploaderId() != $userId) {
    $PRL = new \Gazelle\PermissionRateLimit;
    if (!$PRL->safeFactor($Viewer)) {
        if (!$PRL->safeOvershoot($Viewer)) {
            $DB->prepared_query('
                INSERT INTO ratelimit_torrent
                       (user_id, torrent_id)
                VALUES (?,       ?)
                ', $userId, $torrentId
            );
            if ($Cache->get_value('user_flood_' . $userId)) {
                $Cache->increment('user_flood_' . $userId);
            } else {
                Irc::sendChannel(
                    SITE_URL . "/" . $Viewer->url()
                    . " (" . $Viewer->username() . ")"
                    . " (" . Tools::geoip($_SERVER['REMOTE_ADDR']) . ")"
                    . " accessing "
                    . SITE_URL . $_SERVER['REQUEST_URI']
                    . (!empty($_SERVER['HTTP_REFERER'])? " from ".$_SERVER['HTTP_REFERER'] : '')
                    . ' hit download rate limit',
                    STATUS_CHAN
                );
                $Cache->cache_value('user_429_flood_' . $userId, 1, 3600);
            }
            json_or_error('rate limiting hit on downloading', 429);
        }
    }
}

/* If they are trying use a token on this, we need to make sure they
 * have enough. If so, deduct the number required, note it in the freeleech
 * table and update their cache key.
 */

if (isset($_REQUEST['usetoken']) && $torrent->freeleechStatus() == '0') {
    if (!$Viewer->canSpendFLToken($torrent)) {
        json_or_error('You cannot use tokens here (leeching disabled or already freeleech).');
    }

    // First make sure this isn't already FL, and if it is, do nothing
    if (!$torrent->hasToken($userId)) {
        $tokenCount = $torrent->tokenCount();
        if (!STACKABLE_FREELEECH_TOKENS && $tokenCount > 1) {
            json_or_error('This torrent is too large. Please use the regular DL link.');
        }
        $DB->begin_transaction();
        $DB->prepared_query('
            UPDATE user_flt SET
                tokens = tokens - ?
            WHERE tokens >= ? AND user_id = ?
            ', $tokenCount, $tokenCount, $userId
        );
        if ($DB->affected_rows() == 0) {
            $DB->rollback();
            json_or_error('You do not have enough freeleech tokens. Please use the regular DL link.');
        }

        // Let the tracker know about this
        if (!(new Gazelle\Tracker)->update_tracker('add_token', ['info_hash' => rawurlencode($torrent->infohash()), 'userid' => $userId])) {
            $DB->rollback();
            json_or_error('Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.');
        }
        $DB->prepared_query("
            INSERT INTO users_freeleeches (UserID, TorrentID, Uses, Time)
            VALUES (?, ?, ?, now())
            ON DUPLICATE KEY UPDATE
                Time = VALUES(Time),
                Expired = FALSE,
                Uses = Uses + ?
            ", $userId, $torrentId, $tokenCount, $tokenCount
        );
        $DB->commit();
        $Cache->deleteMulti(["u_$userId", "user_info_heavy_$userId", "users_tokens_$userId"]);
    }
}

$Viewer->registerDownload($torrentId);

if ($torrent->group()->categoryId() == 1 && $torrent->group()->image() != '' && $torrent->uploaderId() != $userId) {
    $Cache->delete_value("user_recent_snatch_$userId");
}

$downloadAsText = ($Viewer->option('DownloadAlt') === '1');
header('Content-Type: ' . ($downloadAsText ? 'text/plain' : 'application/x-bittorrent') . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $torrent->torrentFilename($downloadAsText, MAX_PATH_LEN) . '"');
echo $torrent->torrentBody($Viewer->announceUrl());
