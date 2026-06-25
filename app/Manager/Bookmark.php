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
            ", $new->id, $old->id
        );
        $affected = self::$db->affected_rows();
        $this->pg()->query("
            insert into bookmark_tgroup
                (id_tgroup, id_user, seq, created)
            select $1, id_user, seq, created
            from bookmark_tgroup
            where id_tgroup = $2
            on conflict (id_tgroup, id_user) do nothing
            ", $new->id, $old->id
        );
        return $affected;
    }
}
