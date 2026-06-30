<?php

namespace Gazelle\Manager;

use Gazelle\Task;

class Invite extends \Gazelle\Base {
    protected string $search;

    public function create(
        \Gazelle\User $user,
        string        $email,
        string        $notes,
        string        $reason,
        string        $source,
    ): \Gazelle\Invite|null {
        self::$db->begin_transaction();
        if (!$user->invite()->issueInvite()) {
            return null;
        }
        $inviteKey = randomString();
        self::$db->prepared_query("
            INSERT INTO invites
                   (InviterID, InviteKey, Email, Notes, Reason, Expires)
            VALUES (?,         ?,         ?,     ?,     ?,      now() + INTERVAL 3 DAY)
            ", $user->id, $inviteKey, $email, $notes, $reason
        );
        $invite = new \Gazelle\Invite($inviteKey);
        if (is_number($source)) {
            new InviteSource()->createPendingInviteSource((int)$source, $inviteKey);
        }
        self::$db->commit();
        return $invite;
    }

    public function findUserByKey(string $inviteKey, User $manager = new User()): \Gazelle\User|null {
        return $manager->findById(
            (int)self::$db->scalar("
                SELECT InviterID FROM invites WHERE InviteKey = ?
                ", $inviteKey
            )
        );
    }

    /**
     * Set a text filter on email addresses
     */
    public function setSearch(string $search): static {
        $this->search = $search;
        return $this;
    }

    /**
     * How many pending invites are in circulation?
     *
     * @return int number of invites
     */
    public function totalPending(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM invites WHERE Expires > now()
        ");
    }

    public function emailExists(\Gazelle\User $user, string $email): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM invites
            WHERE InviterID = ?
                AND Email = ?
            ", $user->id, $email
        );
    }

    public function inviteExists(string $key): bool {
        return (bool)self::$db->scalar("
            SELECT 1 FROM invites WHERE InviteKey = ?
            ", $key
        );
    }

    /**
     * Get a page of pending invites
     *
     * @return array list of pending invites [inviter_id, ipaddr, invite_key, expires, email]
     */
    public function pendingInvites(int $limit, int $offset): array {
        if (!isset($this->search)) {
            $where = "/* no email filter */";
            $args = [];
        } else {
            $where = "WHERE i.Email REGEXP ?";
            $args = [$this->search];
        }

        self::$db->prepared_query("
            SELECT i.InviterID AS user_id,
                um.IP          AS ipaddr,
                i.InviteKey    AS `key`,
                i.Expires      AS expires,
                i.Email        AS email,
                ivs.name       AS source_name
            FROM invites i
            INNER JOIN users_main AS um ON (um.ID = i.InviterID)
            LEFT JOIN invite_source_pending ivsp ON (ivsp.invite_key = i.InviteKey)
            LEFT JOIN invite_source ivs USING (invite_source_id)
            $where
            ORDER BY i.Expires DESC
            LIMIT ? OFFSET ?
            ", ...array_merge($args, [$limit, $offset])
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    /**
     * Remove an invite without restoring it to the issuer
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

    /**
     * Expire unused invitations and return them to the user
     */
    public function expire(
        Task|null $task    = null,
        User      $manager = new User(),
    ): int {
        $expired = 0;
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT InviterID AS 'user_id',
                InviteKey    AS 'invite_key'
            FROM invites
            WHERE Expires < now()
        ");
        foreach (self::$db->to_array(false, MYSQLI_ASSOC) as $row) {
            $user = $manager->findById($row['user_id']);
            if (is_null($user)) {
                continue;
            }
            $user->invite()->revoke($row['invite_key']);
            $task?->debug("Expired invite {$row['invite_key']} for user {$user->username()}", $row['user_id']);
            $expired++;
        }
        self::$db->commit();
        return $expired;
    }
}
