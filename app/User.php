<?php

namespace Gazelle;

class User extends Base {

    /** @var int */
    protected  $forceCacheFlush;

    /** @var int */
    protected  $id;

    const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
        $this->forceCacheFlush = false;
    }

    public function info() {
        $this->db->prepared_query("
            SELECT
                um.Username,
                um.IP,
                um.Email,
                um.PermissionID,
                um.Title,
                um.Enabled,
                um.Invites,
                um.can_leech,
                um.Visible,
                um.torrent_pass,
                um.RequiredRatio,
                ui.RatioWatchEnds,
                ui.AdminComment,
                ui.Artist,
                ui.Warned,
                ui.SupportFor,
                ui.RestrictedForums,
                ui.PermittedForums,
                ui.DisableAvatar,
                ui.DisableInvites,
                ui.DisablePoints,
                ui.DisablePosting,
                ui.DisableForums,
                ui.DisableTagging,
                ui.DisableUpload,
                ui.DisableWiki,
                ui.DisablePM,
                ui.DisableIRC,
                ui.DisableRequests,
                sha1(ui.AdminComment) AS CommentHash,
                uls.Uploaded,
                uls.Downloaded,
                p.Level AS Class,
                group_concat(l.PermissionID SEPARATOR ',') AS SecondaryClasses,
                uf.tokens AS FLTokens,
                coalesce(ub.points, 0) AS BonusPoints,
                la.Type,
                CASE WHEN uhaud.UserID IS NULL THEN 0 ELSE 1 END AS unlimitedDownload
            FROM users_main              AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info        AS ui ON (ui.UserID = um.ID)
            INNER JOIN user_flt          AS uf ON (uf.user_id = um.ID)
            LEFT JOIN permissions        AS p ON (p.ID = um.PermissionID)
            LEFT JOIN user_bonus         AS ub ON (ub.user_id = um.ID)
            LEFT JOIN users_levels       AS l ON (l.UserID = um.ID)
            LEFT JOIN locked_accounts    AS la ON (la.UserID = um.ID)
            LEFT JOIN user_has_attr      AS uhaud ON (uhaud.UserID = um.ID
                AND uhaud.UserAttrID = (SELECT ID FROM user_attr WHERE Name = ?))
            WHERE um.ID = ?
            GROUP BY um.ID
            ", 'unlimited-download', $this->id
        );
        return $this->db->has_results() ? $this->db->next_record(MYSQLI_ASSOC, false) : null;
    }

    public function id() {
        return $this->id;
    }

    public function username() {
        return $this->db->scalar("
            SELECT Username
            FROM users_main
            WHERE ID = ?
            ", $this->id
        );
    }

    public function idFromUsername(string $username) {
        return $this->db->scalar("
            SELECT ID
            FROM users_main
            WHERE ID = ?
            ", $username
        );
    }

    public function url() {
        return site_url() . "user.php?id=" . $this->id;
    }

    public function forceCacheFlush($flush = true) {
        return $this->forceCacheFlush = $flush;
    }

    public function flushCache() {
        $this->cache->deleteMulti([
            "enabled_" . $this->id,
            "user_info_" . $this->id,
            "user_info_heavy_" . $this->id,
            "user_stats_" . $this->id,
        ]);
    }

    public function logout() {
        $this->db->prepared_query("
            SELECT SessionID
            FROM users_sessions
            WHERE UserID = ?
            ", $this->id
        );
        $keyStem = 'session_' . $this->id . '_';
        while (list($id) = $this->db->next_record(MYSQLI_NUM)) {
            $this->cache->delete_value($keyStem . $id);
        }
        $this->cache->delete_value("users_sessions_" . $this->id);
        $this->db->prepared_query("
            DELETE FROM users_sessions
            WHERE UserID = ?
            ", $this->id
        );
        return $this->db->affected_rows();
    }

    public function remove() {
        $this->db->prepared_query("
            DELETE um, ui, uls
            FROM users_main um
            INNER JOIN user_info ui ON (ui.UserID = um.ID)
            INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
            WHERE um.ID = ?
            ", $this->id
        );
        $this->cache->delete_value("user_info_$UserID");
    }

    public function mergeLeechStats(string $username, string $staffname) {
        list($mergeId, $up, $down) = $this->db->row("
            SELECT um.ID, uls.Uploaded, uls.Downloaded
            FROM users_main um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            WHERE um.Username = ?
            ", $username
        );
        if (!$mergeId) {
            return null;
        }
        $this->db->prepared_query("
            UPDATE users_leech_stats uls
            INNER JOIN users_info ui USING (UserID)
            SET
                uls.Uploaded = 0,
                uls.Downloaded = 0,
                ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
            WHERE uls.UserID = ?
            ", sprintf("leech stats (up: %s, down: %s, ratio: %s) transferred to %s (%s) by %s\n\n",
                    \Format::get_size($up), \Format::get_size($down), \Format::get_ratio($up, $down),
                    $this->url(), $this->username(), $staffname
            ),
            $mergeId
        );
        return ['up' => $up, 'down' => $down, 'userId' => $mergeId];
    }

    public function lock(int $lockType) {
        $this->db->prepared_query("
            INSERT INTO locked_accounts
                   (UserID, Type)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE Type = ?
            ", $this->id, $lockType, $lockType
        );
        return $this->db->affected_rows() == 1;
    }

    public function unlock() {
        $this->db->prepared_query("
            DELETE FROM locked_accounts WHERE UserID = ?
            ", $this->id
        );
        return $this->db->affected_rows() == 1;
    }

    public function updateTokens(int $n) {
        $this->db->prepared_query('
            UPDATE user_flt SET
                tokens = ?
            WHERE user_id = ?
            ', $n, $this->id
        );
        return $this->db->affected_rows() == 1;
    }

    public function updateIP($oldIP, $newIP) {
        $this->db->prepared_query('
            UPDATE users_history_ips SET
                EndTime = now()
            WHERE EndTime IS NULL
                AND UserID = ?  AND IP = ?
                ', $this->id, $oldIP
        );
        $this->db->prepared_query('
            INSERT IGNORE INTO users_history_ips
                   (UserID, IP)
            VALUES (?,      ?)
            ', $this->id, $newIP
        );
        $this->db->prepared_query('
            UPDATE users_main SET
                IP = ?, ipcc = ?
            WHERE ID = ?
            ', $newIP, \Tools::geoip($newIP), $this->id
        );
        $this->cache->delete_value('user_info_heavy_' . $this->id);
    }

    public function updatePassword($pw, $ipAddr) {
        $this->db->prepared_query('
            UPDATE users_main SET
                PassHash = ?
            WHERE ID = ?
            ', \Users::make_password_hash($pw), $this->id
        );
        if ($this->db->affected_rows() == 1) {
            $this->db->prepared_query('
                INSERT INTO users_history_passwords
                       (UserID, ChangerIP, ChangeTime)
                VALUES (?,      ?,         now())
                ', $this->id, $ipaddr
            );
        }
        return $this->db->affected_rows() == 1;
    }

    public function resetRatioWatch() {
        $this->db->prepared_query("
            UPDATE users_info SET
                RatioWatchEnds = NULL,
                RatioWatchDownload = 0,
                RatioWatchTimes = 0
            WHERE UserID = ?
            ", $this->id
        );
        return $this->db->affected_rows() == 1;
    }

    public function resetIpHistory() {
        $n = 0;
        $this->db->prepared_query("
            DELETE FROM users_history_ips WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_main SET IP = '127.0.0.1' WHERE ID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE xbt_snatched SET IP = '' WHERE uid = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_history_passwords SET ChangerIP = '' WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_history_passkeys SET ChangerIP = '' WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_sessions SET IP = '127.0.0.1' WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        return $n;
    }

    public function resetEmailHistory(string $email, string $ipAddr) {
        $this->db->prepared_query("
            DELETE FROM users_history_emails
            WHERE UserID = ?
            ", $this->id
        );
        $this->db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP)
            VALUES (?,      ?,     ?)
            ", $this->id, $email, $ip
        );
        $this->db->prepared_query("
            UPDATE users_main
            SET Email = ?
            WHERE ID = ?
            ", $email, $this->id
        );
        return $this->db->affected_rows() == 1;
    }

    public function resetSnatched() {
        $this->db->prepared_query("
            DELETE FROM xbt_snatched
            WHERE uid = ?
            ", $this->id
        );
        $this->cache->delete_value("user_recent_snatch_$UserID");
        return $this->db->affected_rows();
    }

    public function resetDownloadList() {
        $this->db->prepared_query('
            DELETE FROM users_downloads
            WHERE UserID = ?
            ', $this->id
        );
        return $this->db->affected_rows();
    }

    public function resetPasskeyHistory(string $oldPasskey, string $newPasskey, string $ipAddr) {
        $this->db->prepared_query("
            INSERT INTO users_history_passkeys
                   (UserID, OldPassKey, NewPassKey, ChangerIP)
            VALUES (?,      ?,          ?,          ?)
            ", $this->id, $oldPasskey, $newPasskey, $ipAddr
        );
    }

    public function supportCount(int $newClassId, int $levelClassId) {
        return (int)$this->db->scalar("
            SELECT count(DISTINCT DisplayStaff)
            FROM permissions
            WHERE ID IN (?, ?)
            ", $newClassId, $levelClassId
        );
    }

    /**
     * toggle Unlimited Download setting
     */
    public function toggleUnlimitedDownload(bool $flag) {
        $this->db->prepared_query('
            SELECT ua.ID
            FROM user_has_attr uha
            INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrID)
            WHERE uha.UserID = ?
                AND ua.Name = ?
            ', $this->id, 'unlimited-download'
        );
        $found = $this->db->has_results();
        // die("flag=$flag, found=$found");
        $toggled = false;
        if (!$flag && $found) {
            list($attrId) = $this->db->next_record();
            $this->db->prepared_query('
                DELETE FROM user_has_attr WHERE UserID = ? AND UserAttrID = ?
                ', $this->id, $attrId
            );
            $toggled = $this->db->affected_rows() == 1;
        }
        elseif ($flag && !$found) {
            $this->db->prepared_query('
                INSERT INTO user_has_attr (UserID, UserAttrID)
                    SELECT ?, ID FROM user_attr WHERE Name = ?
                ', $this->id, 'unlimited-download'
            );
            $toggled = $this->db->affected_rows() == 1;
        }
        return $toggled;
    }

    public function updateCatchup() {
        $this->db->prepared_query("
            UPDATE users_info
            SET CatchupTime = now()
            WHERE UserID = ?
            ", $this->id
        );
        $this->cache->delete_value('user_info_' . $this->id);
        return $this->db->affected_rows() == 1;
    }

    public function permissionList() {
        $this->db->prepared_query('
            SELECT
                p.ID                   AS permId,
                p.Name                 AS permName,
                (l.UserID IS NOT NULL) AS isSet
            FROM permissions AS p
            LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID AND l.UserID = ?)
            WHERE p.Secondary = 1
            ORDER BY p.Name
            ', $this->id
        );
        return $this->db->to_array('permName', MYSQLI_ASSOC, false);
    }

    public function addClasses(array $classes) {
        $this->db->prepared_query("
            INSERT IGNORE INTO users_levels (UserID, PermissionID)
            VALUES " . implode(', ', array_fill(0, count($classes), '(' . $this->id . ', ?)')),
            ...$classes
        );
        return $this->db->affected_rows();
    }

    public function removeClasses(array $classes) {
        $this->db->prepared_query("
            DELETE FROM users_levels
            WHERE UserID = ?
                AND PermissionID IN (" . placeholders($classes) . ")",
            $this->id, ...$classes
        );
        return $this->db->affected_rows();
    }

    public function notifyFilters() {
        if ($this->forceCacheFlush || ($filters = $this->cache->get_value('notify_filters_' . $this->id)) === false) {
            $this->db->prepared_query('
                SELECT ID, Label
                FROM users_notify_filters
                WHERE UserID = ?
                ', $this->id
            );
            $filters = $this->db->to_array('ID');
            $this->cache->cache_value('notify_filters_' . $this->id, $filters, 2592000);
        }
        return $filters;
    }

    public function hasArtistNotification($name) {
        if (($list = $this->cache->get_value('notify_artists_' . $this->id)) === false) {
            $this->db->prepared_query('
                SELECT ID, Artists
                FROM users_notify_filters
                WHERE Label = ?
                    AND UserID = ?
                LIMIT 1
                ', 'Artist notifications', $this->id
            );
            $list = $this->db->next_record(MYSQLI_ASSOC, false);
            $this->cache->cache_value('notify_artists_' . $this->id, $list, 0);
        }
        return stripos($list['Artists'], $name) !== false;
    }

    protected function enabledState() {
        if ($this->forceCacheFlush || ($enabled = $this->cache->get_value('enabled_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT Enabled FROM users_main WHERE ID = ?
                ", $this->id()
            );
            list($enabled) = $this->db->next_record(MYSQLI_NUM);
            $this->cache->cache_value('enabled_' . $this->id, (int)$enabled, 86400 * 3);
        }
        return (int)$enabled;
    }

    public function isUnconfirmed() { return $this->enabledState() == 0; }
    public function isEnabled()     { return $this->enabledState() == 1; }
    public function isDisabled()    { return $this->enabledState() == 2; }

    public function endWarningDate(int $weeks) {
        return $this->db->scalar("
            SELECT coalesce(Warned, now()) + INTERVAL ? WEEK
            FROM users_info
            WHERE UserID = ?
            ", $weeks, $this->id
        );
    }

    public function LastFMUsername() {
        return $this->db->scalar('
            SELECT username
            FROM lastfm_users
            WHERE ID = ?
            ', $this->id
        );
    }

    public function personalCollages() {
        $this->db->prepared_query("
            SELECT ID, Name
            FROM collages
            WHERE UserID = ?
                AND CategoryID = '0'
                AND Deleted = '0'
            ORDER BY Featured DESC, Name ASC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    public function emailHistory() {
        $this->db->prepared_query('
            SELECT DISTINCT Email, IP
            FROM users_history_emails
            WHERE UserID = ?
            ORDER BY Time ASC
            ', $this->id
        );
        return $this->db->to_array();
    }

    public function isFriend($id) {
        $this->db->prepared_query('
            SELECT 1
            FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ', $id, $this->id
        );
        return $this->db->has_results();
    }

    public function requestsBounty() {
        $this->db->prepared_query('
            SELECT COUNT(DISTINCT r.ID), SUM(rv.Bounty)
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON (r.ID = rv.RequestID)
            WHERE r.FillerID = ?
            ', $this->id
        );
        if ($this->db->has_results()) {
            list($filled, $bounty) = $this->db->next_record(MYSQLI_NUM);
        } else {
            $filled = $bounty = 0;
        }
        return [$filled, $bounty];
    }

    public function requestsVotes() {
        $this->db->prepared_query('
            SELECT count(*), coalesce(sum(Bounty), 0)
            FROM requests_votes
            WHERE UserID = ?
            ', $this->id
        );
        if ($this->db->has_results()) {
            list($voted, $bounty) = $this->db->next_record(MYSQLI_NUM);
        } else {
            $voted = $bounty = 0;
        }
        return [$voted, $bounty];
    }

    public function requestsCreated() {
        $this->db->prepared_query('
            SELECT count(*), coalesce(sum(rv.Bounty), 0)
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID AND rv.UserID = r.UserID)
            WHERE r.UserID = ?
            ', $this->id
        );
        if ($this->db->has_results()) {
            list($created, $bounty) = $this->db->next_record(MYSQLI_NUM);
        } else {
            $created = $bounty = 0;
        }
        return [$created, $bounty];
    }

    public function clients() {
        $this->db->prepared_query('
            SELECT DISTINCT useragent
            FROM xbt_files_users
            WHERE uid = ?
            ', $this->id
        );
        return $this->db->collect(0) ?: ['None'];
    }

    protected function getSingleValue($cacheKey, $query) {
        $cacheKey .= '.' . $this->id;
        if ($this->forceCacheFlush || ($value = $this->cache->get_value($cacheKey)) === false) {
            $this->db->prepared_query($query, $this->id);
            list($value) = $this->db->next_record(MYSQLI_NUM);
            $this->cache->cache_value($cacheKey, $value, 3600);
        }
        return $value;
    }

    public function joinDate() {
        return $this->getSingleValue('user-joined', '
            SELECT JoinDate
            FROM users_info
            WHERE UserID = ?
        ');
    }

    public function uploadCount() {
        return $this->getSingleValue('user-upload-count', '
            SELECT count(*)
            FROM torrents
            WHERE UserID = ?
        ');
    }

    public function leechingCounts() {
        return $this->getSingleValue('user-leeching-count', '
            SELECT count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.remaining > 0
                AND x.uid = ?
        ');
    }

    public function seedingCounts() {
        return $this->getSingleValue('user-seeding-count', '
            SELECT count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.remaining = 0
                AND x.uid = ?
        ');
    }

    public function artistsAdded() {
        return $this->getSingleValue('user-artists-count', '
            SELECT count(*)
            FROM torrents_artists
            WHERE UserID = ?
        ');
    }

    public function IRCKey() {
        return $this->getSingleValue('user-irckey', '
            SELECT IRCKey
            FROM users_main
            WHERE ID = ?
        ');
    }

    public function passwordCount() {
        return $this->getSingleValue('user-pw-count', '
            SELECT count(*)
            FROM users_history_passwords
            WHERE UserID = ?
        ');
    }

    public function passkeyCount() {
        return $this->getSingleValue('user-passkey-count', '
            SELECT count(*)
            FROM users_history_passkeys
            WHERE UserID = ?
        ');
    }

    public function siteIPCount() {
        return $this->getSingleValue('user-siteip-count', '
            SELECT count(DISTINCT IP)
            FROM users_history_ips
            WHERE UserID = ?
        ');
    }

    public function trackerIPCount() {
        return $this->getSingleValue('user-trackip-count', "
            SELECT count(DISTINCT IP)
            FROM xbt_snatched
            WHERE uid = ? AND IP != ''
        ");
    }

    public function emailCount() {
        return $this->getSingleValue('user-email-count', '
            SELECT count(*)
            FROM users_history_emails
            WHERE UserID = ?
        ');
    }

    public function addForumWarning(string $comment) {
        $this->db->prepared_query("
            INSERT INTO users_warnings_forums
                   (UserID, Comment)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE Comment = concat(?, ', ', Comment)
            ", $this->id, $comment, $comment
        );
    }

    public function invitedCount() {
        return $this->getSingleValue('user-invites', '
            SELECT count(*)
            FROM users_info
            WHERE Inviter = ?
        ');
    }

    public function pendingInviteCount() {
        return $this->getSingleValue('user-inv-pending', '
            SELECT count(*)
            FROM invites
            WHERE InviterID = ?
        ');
    }

    public function passwordAge() {
        $age = time_diff(
            $this->getSingleValue('user-pw-age', '
                SELECT coalesce(max(uhp.ChangeTime), ui.JoinDate)
                FROM users_info ui
                LEFT JOIN users_history_passwords uhp USING (UserID)
                WHERE ui.UserID = ?
            ')
        );
        return substr($age, 0, strpos($age, " ago"));
    }

    public function supportFor() {
        return $this->getSingleValue('user-support', '
            SELECT SupportFor
            FROM users_info
            WHERE UserID = ?
        ');
    }

    public function artistCommentCount() {
        return $this->getSingleValue('user-comment-artist', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'artists' AND AuthorID = ?
        ");
    }

    public function collageCommentCount() {
        return $this->getSingleValue('user-comment-collage', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'collages' AND AuthorID = ?
        ");
    }

    public function requestCommentCount() {
        return $this->getSingleValue('user-comment-request', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'requests' AND AuthorID = ?
        ");
    }

    public function torrentCommentCount() {
        return $this->getSingleValue('user-comment-torrent', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'torrents' AND AuthorID = ?
        ");
    }

    public function forumWarning() {
        return $this->getSingleValue('user-forumwarn', '
            SELECT Comment
            FROM users_warnings_forums
            WHERE UserID = ?
        ');
    }

    public function collagesCreated() {
        return $this->getSingleValue('user-collage-create', "
            SELECT count(*)
            FROM collages
            WHERE Deleted = '0' AND UserID = ?
        ");
    }

    public function collagesContributed() {
        return $this->getSingleValue('user-collage-contrib', "
            SELECT count(DISTINCT ct.CollageID)
            FROM collages_torrents AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function peerCounts() {
        $this->db->prepared_query("
            SELECT IF(remaining = 0, 'seed', 'leech') AS Type, count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.active = 1
                AND x.uid = ?
            GROUP BY Type
            ", $this->id
        );
        $result = $this->db->to_array(0, MYSQLI_NUM, false);
        return [
            'seeding' => (isset($result['seed']) ? $result['seed'][1] : 0),
            'leeching' => (isset($result['leech']) ? $result['leech'][1] : 0)
        ];
    }

    public function snatchCounts() {
        $this->db->prepared_query('
            SELECT count(*), count(DISTINCT x.fid)
            FROM xbt_snatched AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.uid = ?
            ', $this->id
        );
        list($total, $unique) = $this->db->next_record(MYSQLI_NUM, false);
        return [$total, $unique];
    }

    public function recentSnatches(int $limit = 5) {
        if (($recent = $this->cache->get_value('user_recent_snatch_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT
                    g.ID,
                    g.Name,
                    g.WikiImage
                FROM xbt_snatched AS s
                INNER JOIN torrents AS t ON (t.ID = s.fid)
                INNER JOIN torrents_group AS g ON (t.GroupID = g.ID)
                WHERE g.CategoryID = '1'
                    AND g.WikiImage != ''
                    AND t.UserID != s.uid
                    AND s.uid = ?
                GROUP BY g.ID
                ORDER BY s.tstamp DESC
                LIMIT ?
                ", $this->id, $limit
            );
            $recent = $this->db->to_array() ?? [];
            $artists = \Artists::get_artists($this->db->collect('ID'));
            foreach ($recent as $id => $info) {
                $recent[$id]['Name'] = \Artists::display_artists($artists[$info['ID']], false, true)
                    . $recent[$id]['Name'];
            }
            $this->cache->cache_value('user_recent_snatch_' . $this->id, $recent, 86400 * 3);
        }
        return $recent;
    }

    public function tagSnatchCounts(int $limit = 8) {
        if (($list = $this->cache->get_value('user_tag_snatch_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT tg.Name AS name,
                    tg.ID AS id,
                    count(*) AS n
                FROM torrents_tags tt
                INNER JOIN tags tg ON (tg.ID = tt.TagID)
                INNER JOIN (
                    SELECT DISTINCT t.GroupID
                    FROM xbt_snatched xs
                    INNER JOIN torrents t ON (t.id = xs.fid)
                    WHERE tstamp > unix_timestamp(now() - INTERVAL 6 MONTH)
                        AND xs.uid = ?
                ) SN USING (GroupID)
                GROUP BY tg.ID
                ORDER BY 3 DESC, 1
                LIMIT ?
                ", $this->id, $limit
            );
            $list = $this->db->to_array(false, MYSQLI_ASSOC, false);
            $this->cache->cache_value('user_tag_snatch_' . $this->id, $list, 86400 * 90);
        }
        return $list;
    }

    public function recentUploads(int $limit = 5) {
        if (($recent = $this->cache->get_value('user_recent_up_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT
                    g.ID,
                    g.Name,
                    g.WikiImage
                FROM torrents_group AS g
                INNER JOIN torrents AS t ON (t.GroupID = g.ID)
                WHERE g.WikiImage != ''
                    AND g.CategoryID = '1'
                    AND t.UserID = ?
                GROUP BY g.ID
                ORDER BY t.Time DESC
                LIMIT ?
                ", $this->id, $limit
            );
            $recent = $this->db->to_array() ?? [];
            $artists = \Artists::get_artists($this->db->collect('ID'));
            foreach ($recent as $id => $info) {
                $recent[$id]['Name'] = \Artists::display_artists($artists[$info['ID']], false, true)
                    . $recent[$id]['Name'];
            }
            $this->cache->cache_value('user_recent_up_' . $this->id, $recent, 86400 * 3);
        }
        return $recent;
    }

    public function downloadCounts() {
        $this->db->prepared_query('
            SELECT count(*), count(DISTINCT ud.TorrentID)
            FROM users_downloads AS ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.UserID = ?
            ', $this->id
        );
        list($total, $unique) = $this->db->next_record(MYSQLI_NUM, false);
        return [$total, $unique];
    }

    public function torrentDownloadCount($torrentId) {
        $this->db->prepared_query('
            SELECT count(*)
            FROM users_downloads ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.UserID = ?
                AND ud.TorrentID = ?
            ', $this->id, $torrentId
        );
        list($total) = $this->db->next_record(MYSQLI_NUM, false);
        return $total;
    }

    public function torrentRecentDownloadCount() {
        $this->db->prepared_query('
            SELECT count(*)
            FROM users_downloads ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.Time > now() - INTERVAL 1 DAY
                AND ud.UserID = ?
            ', $this->id
        );
        list($total) = $this->db->next_record(MYSQLI_NUM, false);
        return $total;
    }

    public function downloadSnatchFactor() {
        $this->db->prepared_query('
            SELECT 1
            FROM user_has_attr AS uhaud
            INNER JOIN user_attr as uaud ON (uaud.ID = uhaud.UserAttrID AND uaud.Name = ?)
            WHERE uhaud.UserID = ?
            ', 'unlimited-download', $this->id
        );
        if ($this->db->has_results()) {
            // they are whitelisted, let them through
            return 0.0;
        }
        $stats = $this->cache->get_value('user_rlim_' . $this->id);
        if ($stats === false) {
            $this->db->prepared_query("
                SELECT 'download', count(DISTINCT ud.TorrentID) as nr
                FROM users_downloads ud
                INNER JOIN torrents t ON (t.ID = ud.TorrentID)
                WHERE ud.UserID = ? AND t.UserID != ?
                UNION ALL
                SELECT 'snatch', count(DISTINCT x.fid)
                FROM xbt_snatched AS x
                INNER JOIN torrents AS t ON (t.ID = x.fid)
                WHERE x.uid = ?
                ", $this->id, $this->id, $this->id
            );
            $stats = ['download' => 0, 'snatch' => 0];
            while (list($key, $count) = $this->db->next_record(MYSQLI_ASSOC)) {
                $stats[$key] = $count;
            }
            $stats = $this->cache->cache_value('user_rlim_' . $this->id, $stats, 3600);
        }
        return (1 + $stats['download']) / (1 + $stats['snatch']);
    }

    public static function globalActivityStats($db, $cache) {
        if (($stats = $cache->get_value('stats_users')) === false) {
            $db->prepared_query("
                SELECT
                    sum(ula.last_access > now() - INTERVAL 1 DAY) AS Day,
                    sum(ula.last_access > now() - INTERVAL 1 WEEK) AS Week,
                    sum(ula.last_access > now() - INTERVAL 1 MONTH) AS Month
                FROM users_main um
                INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
                WHERE um.Enabled = '1'
                    AND ula.last_access > now() - INTERVAL 1 MONTH
            ");
            $stats = $db->next_record(MYSQLI_ASSOC);
            $cache->cache_value('stats_users', $stats, 7200);
        }
        return $stats;
    }

    public function buffer() {
        $user = \Users::user_info($this->id);
        $demotion = array_filter(self::demotionCriteria(), function ($v) use ($user) {
            return in_array($user['PermissionID'], $v['From']);
        });
        $criteria = end($demotion);

        $stats = \Users::user_stats($this->id);
        list($votes, $bounty) = $this->requestsVotes();
        $effectiveUpload = $stats['BytesUploaded'] + $bounty;
        if ($criteria) {
            $ratio = $criteria['Ratio'];
        } else {
            $ratio = $stats['RequiredRatio'];
        }

        return [$ratio, $ratio == 0 ? $effectiveUpload : $effectiveUpload / $ratio - $stats['BytesDownloaded']];
    }

    public function nextClass() {
        $user = \Users::user_info($this->id);
        $criteria = self::promotionCriteria()[$user['PermissionID']] ?? null;
        if (!$criteria) {
            return null;
        }

        $stats = \Users::user_stats($this->id);
        $downloaded = (int)$stats['BytesDownloaded'];
        $uploaded = (int)$stats['BytesUploaded'];
        list($votes, $bounty) = $this->requestsVotes();
        $progress = [
            'Class' => \Users::make_class_string($criteria['To']),
            'Requirements' => [
                'Upload' => [$uploaded + $bounty, $criteria['MinUpload'], 'bytes'],
                'Ratio' => [$downloaded == 0 ? '∞' : $uploaded / $downloaded, $criteria['MinRatio'], 'float'],
                'Time' => [
                    $this->joinDate(),
                    $criteria['Weeks'] * 7 * 24 * 60 * 60,
                    'time'
                ],
                'Torrents' => [$this->uploadCount(), $criteria['MinUploads'], 'int'],
            ]
        ];

        if (isset($criteria['Extra'])) {
            foreach ($criteria['Extra'] as $req => $info) {
                $query = str_replace('users_main.ID', '?', $info['Query']);
                $params = array_fill(0, substr_count($query, '?'), $this->id);
                $count = $this->db->scalar($query, ...$params);

                $progress['Requirements'][$req] = [$count, $info['Count'], $info['Type']];
            }
        }
        return $progress;
    }

    public static function demotionCriteria() {
        return [
            USER => [
                'From' => [MEMBER, POWER, ELITE, TORRENT_MASTER, POWER_TM, ELITE_TM, ULTIMATE_TM],
                'To' => USER,
                'Ratio' => 0.65,
                'Upload' => 0
            ],
            MEMBER => [
                'From' => [POWER, ELITE, TORRENT_MASTER, POWER_TM, ELITE_TM, ULTIMATE_TM],
                'To' => MEMBER,
                'Ratio' => 0.95,
                'Upload' => 25 * 1024 * 1024 * 1024
            ],
        ];
    }

    public static function promotionCriteria() {
        $criteria = [
            USER => [
                'From' => USER,
                'To' => MEMBER,
                'MinUpload' => 10 * 1024 * 1024 * 1024,
                'MinRatio' => 0.7,
                'MinUploads' => 0,
                'Weeks' => 1
            ],
            MEMBER => [
                'From' => MEMBER,
                'To' => POWER,
                'MinUpload' => 25 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 5,
                'Weeks' => 2
            ],
            POWER => [
                'From' => POWER,
                'To' => ELITE,
                'MinUpload' => 100 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 50,
                'Weeks' => 4
            ],
            ELITE => [
                'From' => ELITE,
                'To' => TORRENT_MASTER,
                'MinUpload' => 500 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8
            ],
            TORRENT_MASTER => [
                'From' => TORRENT_MASTER,
                'To' => POWER_TM,
                'MinUpload' => 500 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8,
                'Extra' => [
                    'Unique groups' => [
                        'Query' => '
                            SELECT count(DISTINCT GroupID) AS val
                            FROM torrents
                            WHERE UserID = users_main.ID',
                        'Count' => 500,
                        'Type' => 'int'
                    ]
                ]
            ],
            POWER_TM => [
                'From' => POWER_TM,
                'To' => ELITE_TM,
                'MinUpload' => 500 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8,
                'Extra' => [
                    '"Perfect" FLACs' => [
                        'Query' => "
                            SELECT count(ID)
                            FROM torrents
                            WHERE Format = 'FLAC'
                                AND (
                                    (Media = 'CD' AND LogScore = 100)
                                    OR Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT')
                                )
                                AND UserID = users_main.ID",
                        'Count' => 500,
                        'Type' => 'int'
                    ]
                ]
            ],
            ELITE_TM => [
                'From' => ELITE_TM,
                'To' => ULTIMATE_TM,
                'MinUpload' => 2 * 1024 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 2000,
                'Weeks' => 12,
                'Extra' => [
                    '"Perfecter" FLACs' => [
                        'Query' => "
                            SELECT count(DISTINCT t.GroupID, t.RemasterYear, t.RemasterCatalogueNumber, t.RemasterRecordLabel, t.RemasterTitle, t.Media)
                            FROM torrents t
                            WHERE t.Format = 'FLAC'
                                AND (
                                    (t.LogScore = 100 AND t.Media = 'CD')
                                    OR t.Media IN ('Cassette', 'DAT')
                                    OR (t.Media IN ('Vinyl', 'DVD', 'Soundboard', 'SACD', 'BD') AND t.Encoding = '24bit Lossless')
                                )
                                AND t.UserID = users_main.ID",
                        'Count' => 2000,
                        'Type' => 'int'
                    ]
                ]
            ]
        ];

        if (defined('RECOVERY_DB')) {
            $criteria[ELITE_TM]['Extra'][SITE_NAME . ' Upload'] = [
               'Query' => sprintf("
                            SELECT uls.Uploaded + coalesce(b.Bounty, 0) - coalesce(ubl.final, 0)
                            FROM users_leech_stats uls
                            LEFT JOIN
                            (
                                SELECT UserID, sum(Bounty) AS Bounty
                                FROM requests_votes
                                GROUP BY UserID
                            ) b ON (b.UserID = uls.UserID)
                            LEFT JOIN %s.users_buffer_log ubl ON (ubl.opsid = uls.UserID)
                            WHERE uls.UserID = users_main.ID", RECOVERY_DB),
               'Count' => 2 * 1024 * 1024 * 1024 * 1024,
               'Type' => 'bytes'
            ];
        }
        return $criteria;
    }
}
