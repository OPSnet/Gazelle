<?php

namespace Gazelle\Editor;

class UserBookmark extends \Gazelle\Base {
    protected const CACHE_KEY = 'bookmarks_group_ids_%d';

    public function __construct(
        protected readonly int $userId,
    ) {}

    /**
     * Uses (checkboxes) $_POST['remove'] to delete entries.
     * @param $list array<int, int>
     */
    public function remove(array $list): int {
        self::$db->prepared_query("
            DELETE FROM bookmarks_torrents
            WHERE UserID = ?
                AND GroupID IN (" . placeholders($list) . ")
            ", $this->userId, ...$list
        );
        $affected = self::$db->affected_rows();
        $this->pg()->query("
            delete from bookmark_tgroup
            where id_user = $1
                and id_tgroup = any($2)
            ", $this->userId,
            $this->pg()->integerConverter()->output($list)
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->userId));
        return $affected;
    }

    /**
     * Uses $_POST['sort'] values to update entries.
     * @param $list array<int, int>
     */
    public function modify(array $list): int {
        $placeholders = [];
        $args = [];
        foreach ($list as $tgroupId => $sequence) {
            $placeholders[] = '(?, ?, ?)';
            $args = array_merge($args,  [(int)$tgroupId, (int)$sequence, $this->userId]);
        }
        if ($placeholders === []) {
            return 0;
        }
        self::$db->prepared_query("
            INSERT INTO bookmarks_torrents
                (GroupID, Sort, UserID)
            VALUES " . implode(', ', $placeholders) . "
            ON DUPLICATE KEY UPDATE
                Sort = VALUES (Sort)
            ", ...$args
        );
        $affected = self::$db->affected_rows();
        $this->pg()->prepared_query("
            insert into bookmark_tgroup
                (id_tgroup, seq, id_user)
            values " . implode(', ', $placeholders) . "
            on conflict (id_tgroup, id_user) do update set seq = EXCLUDED.seq
            ", ...$args
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->userId));
        return $affected;
    }
}
