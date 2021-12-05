<?php

namespace Gazelle\Manager;

class DNU extends \Gazelle\Base {

    public function dnuList(): array {
        self::$db->prepared_query("
            SELECT d.ID    AS id,
                d.Name     AS name,
                d.Comment  AS comment,
                d.UserID   AS user_id,
                d.Time     AS time
            FROM do_not_upload AS d
            ORDER BY d.Sequence
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
