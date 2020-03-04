<?php

namespace Gazelle;

class User {
    /** @var \DB_MYSQL */
    private $db;

    /** @var \CACHE */
    private $cache;

    private $id;

    public function __construct(\DB_MYSQL $db, \CACHE $cache, $id) {
        $this->db = $db;
        $this->cache = $cache;
        $this->id = $id;
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
            SELECT
                COUNT(DISTINCT r.ID),
                SUM(rv.Bounty)
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON (r.ID = rv.RequestID)
            WHERE r.FillerID = ?
            ', $this->id
        );
        if ($this->db->has_results()) {
            list($filled, $bounty) = $this->db->next_record();
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
            list($voted, $bounty) = $this->db->next_record();
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
            list($created, $bounty) = $this->db->next_record();
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
        return $this->db->collect(0);
    }

    protected function getSingleValue($query) {
        $this->db->prepared_query($query, $this->id);
        list($value) = $this->db->next_record();
        return $value;
    }

    public function uploadCount() {
        return $this->getSingleValue('
            SELECT count(*)
            FROM torrents
            WHERE UserID = ?
        ');
    }

    public function artistsAdded() {
        return $this->getSingleValue('
            SELECT count(*)
            FROM torrents_artists
            WHERE UserID = ?
        ');
    }

    public function IRCKey() {
        return $this->getSingleValue('
            SELECT IRCKey
            FROM users_main
            WHERE ID = ?
        ');
    }

    public function passwordCount() {
        return $this->getSingleValue('
            SELECT count(*)
            FROM users_history_passwords
            WHERE UserID = ?
        ');
    }

    public function passkeyCount() {
        return $this->getSingleValue('
            SELECT count(*)
            FROM users_history_passkeys
            WHERE UserID = ?
        ');
    }

    public function siteIPCount() {
        return $this->getSingleValue('
            SELECT count(DISTINCT IP)
            FROM users_history_ips
            WHERE UserID = ?
        ');
    }

    public function trackerIPCount() {
        return $this->getSingleValue("
            SELECT count(DISTINCT IP)
            FROM xbt_snatched
            WHERE uid = ? AND IP != ''
        ");
    }

    public function emailCount() {
        return $this->getSingleValue('
            SELECT count(*)
            FROM users_history_emails
            WHERE UserID = ?
        ');
    }

    public function pendingInviteCount() {
        return $this->getSingleValue('
            SELECT count(*)
            FROM invites
            WHERE InviterID = ?
        ');
    }

    public function passwordAge() {
        $age = time_diff(
            $this->getSingleValue('
                SELECT coalesce(max(uhp.ChangeTime), ui.JoinDate)
                FROM users_info ui
                LEFT JOIN users_history_passwords uhp USING (UserID)
                WHERE ui.UserID = ?
            ')
        );
        return substr($age, 0, strpos($age, " ago"));
    }

    public function supportFor() {
        return $this->getSingleValue('
            SELECT SupportFor
            FROM users_info
            WHERE UserID = ?
        ');
    }

    public function forumWarning() {
        return $this->getSingleValue('
            SELECT Comment
            FROM users_warnings_forums
            WHERE UserID = ?
        ');
    }

    public function xxx() {
        return $this->getSingleValue('
        ');
    }
}
