<?php

namespace Gazelle\Manager;

class Bookmark extends \Gazelle\Base {
    public function merge(int $oldId, int $newId): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO bookmarks_torrents
                  (UserID, GroupID, Time, Sort)
            SELECT UserID,       ?, Time, Sort
            FROM bookmarks_torrents
            WHERE GroupID = ?
            ", $newId, $oldId
        );
        return self::$db->affected_rows();
    }
}
