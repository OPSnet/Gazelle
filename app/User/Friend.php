<?php

namespace Gazelle\User;

class Friend extends \Gazelle\BaseUser {

    public function isFriend(int $friendId): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ", $this->user->id(), $friendId
        );
    }

    public function add(int $friendId): bool {
        if (!self::$db->scalar("SELECT 1 FROM users_main WHERE ID = ?", $friendId)) {
            return false;
        }
        self::$db->prepared_query("
            INSERT IGNORE INTO friends
                   (UserID, FriendID)
            VALUES (?,      ?)
            ", $this->user->id(), $friendId
        );
        return self::$db->affected_rows() === 1;
    }

    public function addComment(int $friendId, string $comment): bool {
        self::$db->prepared_query("
            UPDATE friends SET
                Comment = ?
            WHERE UserID = ?
                AND FriendID = ?
            ", $comment, $this->user->id(), $friendId
        );
        return self::$db->affected_rows() === 1;
    }

    public function remove(int $friendId): bool {
        self::$db->prepared_query("
            DELETE FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ", $this->user->id(), $friendId
        );
        return self::$db->affected_rows() === 1;
    }

    public function total(): int {
        return self::$db->scalar("
            SELECT count(*) FROM friends WHERE UserID = ?
            ", $this->user->id()
        );
    }

    public function page(\Gazelle\Manager\User $userMan, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT f.FriendID AS id,
                f.Comment as comment
            FROM friends AS f
            INNER JOIN users_main AS um ON (um.ID = f.FriendID)
            WHERE f.UserID = ?
            ORDER BY um.Username
            LIMIT ? OFFSET ?
            ", $this->user->id(), $limit, $offset
        );
        $list = self::$db->to_array('id', MYSQLI_ASSOC, false);
        foreach (array_keys($list) as $id) {
            $list[$id]['user'] = new \Gazelle\User($id);
            $list[$id]['avatar'] = $userMan->avatarMarkup($this->user, $list[$id]['user']);
        }
        return $list;
    }

    public function userList(): array {
        self::$db->prepared_query("
            SELECT f.FriendID,
                u.Username
            FROM friends AS f
            RIGHT JOIN users_main AS u ON (u.ID = f.FriendID)
            WHERE f.UserID = ?
            ORDER BY u.Username ASC
            ", $this->user->id()
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
