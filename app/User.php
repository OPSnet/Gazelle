<?php

namespace Gazelle;

class User {
    /** @var \DB_MYSQL */
    protected  $db;

    /** @var \CACHE */
    protected  $cache;

    /** @var int */
    protected  $forceCacheFlush;

    /** @var int */
    protected  $id;

    public function __construct(\DB_MYSQL $db, \CACHE $cache, int $id) {
        $this->db = $db;
        $this->cache = $cache;
        $this->id = $id;
        $this->forceCacheFlush = false;
    }

    public function id() {
        return $this->id;
    }

    public function forceCacheFlush($flush = true) {
        return $this->forceCacheFlush = $flush;
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
                   (UserID, IP, StartTime)
            VALUES (?,      ?,  now())
            ', $this->id, $newIP
        );
        $this->db->prepared_query('
            UPDATE users_main SET
                IP = ?, ipcc = ?
            WHERE ID = ?
            ', $newIP, \Tools::geoip($newIP), $this->id
        );
        $this->cache->begin_transaction('user_info_heavy_' . $this->id);
        $this->cache->update_row(false, ['IP' => $newIP]);
        $this->cache->commit_transaction(0);
    }

    public function updatePassword($pw, $ipAddr) {
        $this->db->prepared_query('
            INSERT INTO users_history_passwords
                   (UserID, ChangerIP, ChangeTime)
            VALUES (?,      ?,         now())
            ', $this->id, $ipaddr
        );
        $this->db->prepared_query('
            UPDATE users_main SET
                PassHash = ?
            WHERE ID = ?
            ', \Users::make_password_hash($pw), $this->id
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

    public function LastFMUsername() {
        $name = $this->db->scalar('
            SELECT username
            FROM lastfm_users
            WHERE ID = ?
            ', $this->id
        );
        return $name;
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
            SELECT count(*), sum(Bounty)
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

    public function uploadCount() {
        return $this->getSingleValue('user-upload-count', '
            SELECT count(*)
            FROM torrents
            WHERE UserID = ?
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

    public function forumWarning() {
        return $this->getSingleValue('user-forumwarn', '
            SELECT Comment
            FROM users_warnings_forums
            WHERE UserID = ?
        ');
    }

    public function invitedCount() {
        return $this->getSingleValue('user-invites', '
            SELECT count(*)
            FROM users_info
            WHERE Inviter = ?
        ');
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
}
