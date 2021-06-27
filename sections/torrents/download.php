<?php

use \Gazelle\Util\Irc;

// The torrent_pass is not passed if we're using AJAX, and optional if we're
// going through the site regularly as we can validate based on cookie. In
// these cases, we want to then enforce_login, but if the user IS using a torrent
// pass, we have to assume it is coming from a seedbox or other non-authenticated
// environment so we cannot enforce_login.
if (defined('AJAX') || !isset($_REQUEST['torrent_pass'])) {
    enforce_login();
} else {
    $Viewer = (new Gazelle\Manager\User)->findByAnnounceKey($_REQUEST['torrent_pass']);
}

if (is_null($Viewer)) {
    json_or_error('missing user', 404);
} elseif (!$Viewer->isEnabled() || $Viewer->isLocked()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
$torrentId = (int)$_REQUEST['id'];
if (!$torrentId) {
    json_or_error(0, 'missing torrentid');
}

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

$key = "tdown_$torrentId";
if (($info = $Cache->get_value($key)) === false) {
    $info = $DB->rowAssoc("
        SELECT t.ID as TorrentID,
            t.GroupID,
            t.Media,
            t.Format,
            t.Encoding,
            if(t.RemasterYear = 0, tg.Year, t.RemasterYear) AS Year,
            t.Size,
            t.FreeTorrent,
            t.info_hash,
            t.UserID AS uploaderId,
            tg.CategoryID,
            tg.Name,
            tg.WikiImage
        FROM torrents AS t
        INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
        WHERE t.ID = ?
        ", $torrentId
    );
    if (!isset($info['Media'])) {
        json_or_error('invalid torrentid', 404);
    }
    $artists = Artists::get_artist($info['GroupID']);
    $info['Artist'] = Artists::display_artists($artists, false, false, false);
    $Cache->cache_value($key, $info, 0);
}
if (is_null($info)) {
    json_or_error('could not find torrent', 404);
}

/* If this is not their torrent, then see if they have downloaded too
 * many files, compared to completely snatched items. If that is too
 * high, and they have already downloaded too many files recently, then
 * stop them. Exception: always allowed if they are using FL tokens.
 */
$userId = $Viewer->id();
if (!(isset($_REQUEST['usetoken']) && $_REQUEST['usetoken']) && $info['uploaderId'] != $userId) {
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
                    "user.php?id=" . $userId
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
if ($_REQUEST['usetoken'] && $info['FreeTorrent'] == '0') {
    if (!$Viewer->canLeech()) {
        json_or_error('You cannot use tokens while leeching is disabled.');
    }

    // First make sure this isn't already FL, and if it is, do nothing
    if (!Torrents::has_token($torrentId)) {
        if (!STACKABLE_FREELEECH_TOKENS && $info['Size'] >= BYTES_PER_FREELEECH_TOKEN) {
            json_or_error('This torrent is too large. Please use the regular DL link.');
        }
        $tokensToUse = (int)ceil($info['Size'] / BYTES_PER_FREELEECH_TOKEN);
        $DB->begin_transaction();
        $DB->prepared_query('
            UPDATE user_flt SET
                tokens = tokens - ?
            WHERE tokens >= ? AND user_id = ?
            ', $tokensToUse, $tokensToUse, $userId
        );
        if ($DB->affected_rows() == 0) {
            $DB->rollback();
            json_or_error('You do not have enough freeleech tokens. Please use the regular DL link.');
        }

        // Let the tracker know about this
        if (!(new Gazelle\Tracker)->update_tracker('add_token', ['info_hash' => rawurlencode($info['info_hash']), 'userid' => $userId])) {
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
            ", $userId, $torrentId, $tokensToUse, $tokensToUse
        );
        $DB->commit();
        $Cache->deleteMulti(["u_$userId", "user_info_heavy_$userId", "users_tokens_$userId"]);
    }
}

$DB->prepared_query("
    INSERT IGNORE INTO users_downloads (UserID, TorrentID, Time)
    VALUES (?, ?, now())
    ", $userId, $torrentId
);
Torrents::set_snatch_update_time($userId, Torrents::SNATCHED_UPDATE_AFTERDL);

if ($info['CategoryID'] == '1' && $info['WikiImage'] != '' && $info['uploaderId'] != $userId) {
    $Cache->delete_value("user_recent_snatch_$userId");
}
$Cache->delete_value('user_rlim_' . $userId);

$torrent = (new Gazelle\Manager\Torrent)->findById($torrentId);

$downloadAsText = ($Viewer->option('DownloadAlt') === '1');
header('Content-Type: ' . ($downloadAsText ? 'text/plain' : 'application/x-bittorrent') . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $torrent->torrentFilename($downloadAsText, MAX_PATH_LEN) . '"');
echo $torrent->torrentBody($Viewer->announceUrl());
