<?php

namespace Gazelle\User;

class Friend extends \Gazelle\BaseUser {
    final public const tableName = 'friends';

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    public function isFriend(\Gazelle\User $friend): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ", $this->user->id(), $friend->id()
        );
    }

    public function isMutual(\Gazelle\User $friend): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM friends a
            INNER JOIN friends b ON (b.UserID = a.FriendID AND b.FriendID = ?)
            WHERE a.UserID = ?
                AND a.FriendID = ?
            ", $this->user->id(), $this->user->id(), $friend->id()
        );
    }

    public function add(\Gazelle\User $friend): int {
        if ($this->user->id() === $friend->id()) {
            return -1;
        }
        self::$db->prepared_query("
            INSERT IGNORE INTO friends
                   (UserID, FriendID)
            VALUES (?,      ?)
            ", $this->user->id(), $friend->id()
        );
        return self::$db->affected_rows();
    }

    public function addComment(\Gazelle\User $friend, string $comment): int {
        self::$db->prepared_query("
            UPDATE friends SET
                Comment = ?
            WHERE UserID = ?
                AND FriendID = ?
            ", $comment, $this->user->id(), $friend->id()
        );
        return self::$db->affected_rows();
    }

    public function remove(\Gazelle\User $friend): int {
        self::$db->prepared_query("
            DELETE FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ", $this->user->id(), $friend->id()
        );
        return self::$db->affected_rows();
    }

    public function total(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM friends WHERE UserID = ?
            ", $this->user->id()
        );
    }

    public function page(\Gazelle\Manager\User $userMan, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT a.FriendID AS id,
                a.Comment     AS comment,
                CASE WHEN b.UserID IS NULL THEN 0 ELSE 1 END AS mutual
            FROM friends a
            INNER JOIN users_main AS um ON (um.ID = a.FriendID)
            LEFT JOIN friends b ON (b.UserID = a.FriendID AND b.FriendID = ?)
            WHERE a.UserID = ?
            ORDER BY um.Username
            LIMIT ? OFFSET ?
            ", $this->user->id(), $this->user->id(), $limit, $offset
        );
        $list = self::$db->to_array('id', MYSQLI_ASSOC, false);
        foreach (array_map('intval', array_keys($list)) as $id) {
            $list[$id]['user'] = $userMan->findById($id);
        }
        return $list;
    }

    public function userList(): array {
        self::$db->prepared_query("
            SELECT f.FriendID,
                u.Username
            FROM friends AS f
            INNER JOIN users_main AS u ON (u.ID = f.FriendID)
            WHERE f.UserID = ?
            ORDER BY u.Username ASC
            ", $this->user->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
