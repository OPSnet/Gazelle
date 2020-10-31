<?php

namespace Gazelle\Manager;

class Invite extends \Gazelle\Base {

    protected $search;

    /**
     * Set a text filter on email addresses
     *
     * @param string email address fragment
     */
    public function setSearch(string $search) {
        $this->search = $search;
        return $this;
    }

    /**
     * How many pending invites are in circulation?
     *
     * @return int number of invites
     */
    public function totalPending(): int {
        return $this->db->scalar("
            SELECT count(*) FROM invites WHERE Expires > now()
        ");
    }

    /**
     * Get a page of pending invites
     *
     * @param string limit (e.g. "20, 60" for LIMIT 20 OFFSET 60)
     * @return array list of pending invites [inviter_id, ipaddr, invite_key, expires, email]
     */
    public function pendingInvites(string $limit): array {
        if (is_null($this->search)) {
            $where = "/* no email filter */";
            $args = [];
        } else {
            $where = "WHERE i.Email REGEXP ?";
            $args = [$this->search];
        }

        $this->db->prepared_query("
            SELECT i.InviterID AS user_id,
                um.IP AS ipaddr,
                i.InviteKey AS `key`,
                i.Expires AS expires,
                i.Email AS email
            FROM invites AS i
            INNER JOIN users_main AS um ON (um.ID = i.InviterID)
            $where
            ORDER BY i.Expires DESC
            LIMIT $limit
            ", ...$args
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Remove an invite
     *
     * @param string invite key
     * @return bool true if something was actually removed
     */
    public function removeInviteKey(string $key): bool {
        $this->db->prepared_query("
            DELETE FROM invites
            WHERE InviteKey = ?
            ", trim($key)
        );
        return $this->db->affected_rows() !== 0;
    }
}
