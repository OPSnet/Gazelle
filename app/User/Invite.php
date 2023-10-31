<?php

namespace Gazelle\User;

/**
 * The functionality for a user to invite other users is delegated to this class.
 */

class Invite extends \Gazelle\BaseUser {
    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    public function issueInvite(): bool {
        if ($this->user()->permitted('site_send_unlimited_invites')) {
            return true;
        }
        self::$db->prepared_query("
            UPDATE users_main SET
                Invites = GREATEST(Invites, 1) - 1
            WHERE ID = ?
            ", $this->id()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected > 0;
    }

    /**
     * Revoke an active invitation (restore previous invite total)
     */
    public function revoke(string $key): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM invites WHERE InviteKey = ?
            ", $key
        );
        if (self::$db->affected_rows() == 0) {
            self::$db->rollback();
            return false;
        }
        if ($this->user()->permitted('site_send_unlimited_invites')) {
            self::$db->commit();
            return true;
        }

        self::$db->prepared_query("
            UPDATE users_main SET
                Invites = Invites + 1
            WHERE ID = ?
            ", $this->id()
        );
        self::$db->commit();
        $this->user()->flush();
        return true;
    }

    public function pendingTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM invites WHERE InviterID = ?
            ", $this->id()
        );
    }

    public function pendingList(): array {
        self::$db->prepared_query("
            SELECT InviteKey AS invite_key,
                Email        AS email,
                Expires      AS expires
            FROM invites
            WHERE InviterID = ?
            ORDER BY Expires
            ", $this->id()
        );
        return self::$db->to_array('invite_key', MYSQLI_ASSOC, false);
    }

    public function total(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM users_main WHERE inviter_user_id = ?
            ", $this->id()
        );
    }

    public function page(string $orderBy, string $direction, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            LEFT  JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            WHERE um.inviter_user_id = ?
            ORDER BY $orderBy $direction
            LIMIT ? OFFSET ?
            ", $this->id(), $limit, $offset
        );
        return self::$db->collect(0, false);
    }
}
