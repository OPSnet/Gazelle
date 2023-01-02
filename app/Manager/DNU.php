<?php

namespace Gazelle\Manager;

class DNU extends \Gazelle\Base {
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

    public function hasNewForUser(\Gazelle\User  $user): bool {
        return (bool)self::$db->scalar("
            SELECT if(max(Time) IS NULL OR max(Time) < ?, 1, 0)
            FROM torrents
            WHERE UserID = ?
            ", $this->latest(), $user->id()
        );
    }
}
