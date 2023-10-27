<?php

namespace Gazelle\Manager;

class DNU extends \Gazelle\Base {
    public function create(
        string        $name,
        string        $comment,
        \Gazelle\User $user,
    ): int {
        self::$db->prepared_query("
            INSERT INTO do_not_upload
                   (Name, Comment, UserID, Sequence)
            VALUES (?,    ?,       ?,      9999)
            ", $name, $comment, $user->id()
       );
       return self::$db->inserted_id();
   }

    public function modify(
        int           $id,
        string        $name,
        string        $comment,
        \Gazelle\User $user,
    ): int {
        self::$db->prepared_query("
            UPDATE do_not_upload SET
                Name    = ?,
                Comment = ?,
                UserID  = ?
            WHERE ID = ?
            ", $name, $comment, $user->id(), $id
        );
        return self::$db->affected_rows();
    }

    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM do_not_upload WHERE ID = ?
            ", $id
        );
        return self::$db->affected_rows();
    }

    public function reorder(array $list): int {
        $sequence = 0;
        $case = [];
        $args = [];
        foreach ($list as $id) {
            $case[] = "WHEN ID = ? THEN ?";
            array_push($args, $id, ++$sequence);
        }
        $sql = "UPDATE do_not_upload SET Sequence = CASE "
            . implode(' ', $case)
            . ' END';
        self::$db->prepared_query($sql, ...$args);
        return self::$db->affected_rows();
    }

    public function dnuList(): array {
        self::$db->prepared_query("
            SELECT d.ID   AS id,
                d.Name    AS name,
                d.Comment AS comment,
                d.UserID  AS user_id,
                d.Time    AS time,
                if(d.Time > now() - INTERVAL 1 MONTH, 1, 0)
                          AS is_new
            FROM do_not_upload AS d
            ORDER BY d.Sequence
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function latest(): string {
        return (string)self::$db->scalar("
            SELECT max(Time) FROM do_not_upload
        ");
    }

    public function hasNewForUser(\Gazelle\User $user): bool {
        return (bool)self::$db->scalar("
            SELECT if(max(Time) IS NULL OR max(Time) < ?, 1, 0)
            FROM torrents
            WHERE UserID = ?
            ", $this->latest(), $user->id()
        );
    }
}
