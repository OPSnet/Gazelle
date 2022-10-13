<?php

namespace Gazelle\Manager;

class Invite extends \Gazelle\Base {

    protected $search;

    /**
     * Set a text filter on email addresses
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
        return self::$db->scalar("
            SELECT count(*) FROM invites WHERE Expires > now()
        ");
    }

    public function inviteExists(string $key): bool {
        return (bool)self::$db->scalar("
            SELECT InviteKey FROM invites WHERE InviteKey = ?
            ", $key
        );
    }

    /**
     * Get a page of pending invites
     *
     * @return array list of pending invites [inviter_id, ipaddr, invite_key, expires, email]
     */
    public function pendingInvites(int $limit, int $offset): array {
        if (is_null($this->search)) {
            $where = "/* no email filter */";
            $args = [];
        } else {
            $where = "WHERE i.Email REGEXP ?";
            $args = [$this->search];
        }

        self::$db->prepared_query("
            SELECT i.InviterID AS user_id,
                um.IP AS ipaddr,
                i.InviteKey AS `key`,
                i.Expires AS expires,
                i.Email AS email
            FROM invites AS i
            INNER JOIN users_main AS um ON (um.ID = i.InviterID)
            $where
            ORDER BY i.Expires DESC
            LIMIT ? OFFSET ?
            ", ...array_merge($args, [$limit, $offset])
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Remove an invite
     *
     * @return bool true if something was actually removed
     */
    public function removeInviteKey(string $key): bool {
        self::$db->prepared_query("
            DELETE FROM invites
            WHERE InviteKey = ?
            ", trim($key)
        );
        return self::$db->affected_rows() !== 0;
    }
}
