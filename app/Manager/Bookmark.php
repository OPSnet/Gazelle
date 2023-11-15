<?php

namespace Gazelle\Manager;

class Bookmark extends \Gazelle\Base {
    public function merge(\Gazelle\TGroup $old, \Gazelle\TGroup $new): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO bookmarks_torrents
                  (UserID, GroupID, Time, Sort)
            SELECT UserID,       ?, Time, Sort
            FROM bookmarks_torrents
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        return self::$db->affected_rows();
    }
}
