<?php

use \Gazelle\Util\Irc;

if (defined('AJAX') || (!isset($_REQUEST['authkey']) || !isset($_REQUEST['torrent_pass']))) {
    enforce_login();
    $TorrentPass = $LoggedUser['torrent_pass'];
    $DownloadAlt = $LoggedUser['DownloadAlt'];
    $UserID = $LoggedUser['ID'];
    $AuthKey = $LoggedUser['AuthKey'];
    $HttpsTracker = $LoggedUser['HttpsTracker'];
} else {
    if (strpos($_REQUEST['torrent_pass'], '_') !== false) {
        error(404);
    }

    $UserInfo = $Cache->get_value('user_'.$_REQUEST['torrent_pass']);
    if (!is_array($UserInfo)) {
        $DB->prepared_query("
            SELECT ID, DownloadAlt, SiteOptions, la.UserID
            FROM users_main AS m
            INNER JOIN users_info AS i ON (i.UserID = m.ID)
            LEFT JOIN locked_accounts AS la ON (la.UserID = m.ID)
            WHERE m.Enabled = '1' AND m.torrent_pass = ?
            ", $_REQUEST['torrent_pass']
        );
        $UserInfo = $DB->next_record(MYSQLI_NUM, [2]);
        $SiteOptions = array_merge(Users::default_site_options(), unserialize_array($UserInfo[2]));
        $UserInfo[2] = $SiteOptions['HttpsTracker'];
        $Cache->cache_value('user_'.$_REQUEST['torrent_pass'], $UserInfo, 3600);
    }
    $UserInfo = [$UserInfo];
    list($UserID, $DownloadAlt, $HttpsTracker, $Locked) = array_shift($UserInfo);
    if (!$UserID) {
        error(0);
    }
    $TorrentPass = $_REQUEST['torrent_pass'];
    $AuthKey = $_REQUEST['authkey'];

    if ($Locked == $UserID) {
        header('HTTP/1.1 403 Forbidden');
        die();
    }
}

$HttpsTracker = $HttpsTracker || isset($_REQUEST['ssl']);

$TorrentID = $_REQUEST['id'];

if (!is_number($TorrentID)) {
    json_or_error(0, 'missing torrentid');
}

$User = new Gazelle\User($UserID);

/* uTorrent Remote and various scripts redownload .torrent files periodically.
 * To prevent this retardation from blowing bandwidth etc., let's block it
 * if the .torrent file has been downloaded four times before.
 */
$ScriptUAs = ['BTWebClient*', 'Python-urllib*', 'python-requests*', 'uTorrent*'];
if (Misc::in_array_partial($_SERVER['HTTP_USER_AGENT'], $ScriptUAs)) {
    if ($User->torrentDownloadCount($TorrentID) > 3) {
        $Msg = 'You have already downloaded this torrent file four times. If you need to download it again, please do so from your browser.';
        json_or_error($Msg, $Msg, true);
    }
}

$Info = $Cache->get_value('torrent_download_'.$TorrentID);
if (!is_array($Info) || !array_key_exists('PlainArtists', $Info) || empty($Info[10])) {
    $DB->prepared_query('
        SELECT
            t.Media,
            t.Format,
            t.Encoding,
            IF(t.RemasterYear = 0, tg.Year, t.RemasterYear),
            tg.ID AS GroupID,
            tg.Name,
            tg.WikiImage,
            tg.CategoryID,
            t.Size,
            t.FreeTorrent,
            t.info_hash,
            t.UserID
        FROM torrents AS t
        INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
        WHERE t.ID = ?
        ', $TorrentID
    );
    if (!$DB->has_results()) {
        json_or_error('invalid torrentid', 404);

    }
    $Info = [$DB->next_record(MYSQLI_NUM, [4, 5, 6, 10])];
    $Artists = Artists::get_artist($Info[0][4]);
    $Info['Artists'] = Artists::display_artists($Artists, false, true);
    $Info['PlainArtists'] = Artists::display_artists($Artists, false, true, false);
    $Cache->cache_value("torrent_download_$TorrentID", $Info, 0);
}
if (!is_array($Info[0])) {
    json_or_error('could not find torrent', 404);
}
[$Media, $Format, $Encoding, $Year, $GroupID, $Name, $Image, $CategoryID, $Size, $FreeTorrent, $InfoHash, $TorrentUploaderID]
    = array_shift($Info); // used for generating the filename
$Artists = $Info['Artists'];

/* If this is not their torrent, then see if they have downloaded too
 * many files, compared to completely snatched items. If that is too
 * high, and they have already downloaded too many files recently, then
 * stop them. Exception: always allowed if they are using FL tokens.
 */
if (!(isset($_REQUEST['usetoken']) && $_REQUEST['usetoken']) && $TorrentUploaderID != $UserID) {
    $PRL = new \Gazelle\PermissionRateLimit;
    if (!$PRL->safeFactor($User)) {
        if (!$PRL->safeOvershoot($User)) {
            $DB->prepared_query('
                INSERT INTO ratelimit_torrent
                       (user_id, torrent_id)
                VALUES (?,       ?)
                ', $UserID, $TorrentID
            );
            if ($Cache->get_value('user_flood_' . $UserID)) {
                $Cache->increment('user_flood_' . $UserID);
            } else {
                Irc::sendChannel(
                    "user.php?id=" . $UserID
                    . " (" . $User->username() . ")"
                    . " (" . Tools::geoip($_SERVER['REMOTE_ADDR']) . ")"
                    . " accessing "
                    . SITE_URL . $_SERVER['REQUEST_URI']
                    . (!empty($_SERVER['HTTP_REFERER'])? " from ".$_SERVER['HTTP_REFERER'] : '')
                    . ' hit download rate limit',
                    STATUS_CHAN
                );
                $Cache->cache_value('user_429_flood_' . $UserID, 1, 3600);
            }
            json_or_error('rate limiting hit on downloading', 429);
        }
    }
}

/* If they are trying use a token on this, we need to make sure they
 * have enough. If so, deduct the number required, note it in the freeleech
 * table and update their cache key.
 */
if ($_REQUEST['usetoken'] && $FreeTorrent == '0') {
    if (!$User->canLeech()) {
        json_or_error('You cannot use tokens while leeching is disabled.');
    }

    // First make sure this isn't already FL, and if it is, do nothing
    if (!Torrents::has_token($TorrentID)) {
        if (!STACKABLE_FREELEECH_TOKENS && $Size >= BYTES_PER_FREELEECH_TOKEN) {
            json_or_error('This torrent is too large. Please use the regular DL link.');
        }
        $TokensToUse = (int)ceil($Size / BYTES_PER_FREELEECH_TOKEN);
        $DB->begin_transaction();
        $DB->prepared_query('
            UPDATE user_flt SET
                tokens = tokens - ?
            WHERE tokens >= ? AND user_id = ?
            ', $TokensToUse, $TokensToUse, $UserID
        );
        if ($DB->affected_rows() == 0) {
            $DB->rollback();
            json_or_error('You do not have any freeleech tokens left. Please use the regular DL link.');
        }

        // Let the tracker know about this
        if (!(new Gazelle\Tracker)->update_tracker('add_token', ['info_hash' => rawurlencode($InfoHash), 'userid' => $UserID])) {
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
            ", $UserID, $TorrentID, $TokensToUse, $TokensToUse
        );
        $DB->commit();
        $Cache->deleteMulti(["u_$UserID", "user_info_heavy_$UserID", "users_tokens_$UserID"]);
    }
}

if ($CategoryID == '1' && $Image != '' && $TorrentUploaderID != $UserID) {
    $Cache->delete_value("user_recent_snatch_$UserID");
}

$DB->prepared_query("
    INSERT IGNORE INTO users_downloads (UserID, TorrentID, Time)
    VALUES (?, ?, now())
    ", $UserID, $TorrentID);

Torrents::set_snatch_update_time($UserID, Torrents::SNATCHED_UPDATE_AFTERDL);
$filer = new \Gazelle\File\Torrent;
$Contents = $filer->get($TorrentID);
$Cache->delete_value('user_rlim_' . $UserID);

$FileName = TorrentsDL::construct_file_name($Info['PlainArtists'], $Name, $Year, $Media, $Format, $Encoding, $TorrentID, $DownloadAlt);
if ($DownloadAlt) {
    header('Content-Type: text/plain; charset=utf-8');
}
elseif (!$DownloadAlt || $Failed) {
    header('Content-Type: application/x-bittorrent; charset=utf-8');
}
header('Content-disposition: attachment; filename="'.$FileName.'"');

echo TorrentsDL::get_file($Contents, $User->announceUrl(), $TorrentID);

define('SKIP_NO_CACHE_HEADERS', 1);
