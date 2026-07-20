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
        $affected = $this->pg()->query("
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
        if ($list === []) {
            return 0;
        }
        $args         = [$this->userId];
        $placeholders = [];
        foreach ($list as $tgroupId => $sequence) {
            $placeholders[] = sprintf(
                '($1,$%d,$%d)',
                count($args) + 1, count($args) + 2,
            );
            $args = array_merge($args,  [(int)$tgroupId, (int)$sequence]);
        }
        $affected = $this->pg()->query("
            insert into bookmark_tgroup
                (id_user, id_tgroup, seq)
            values " . implode(', ', $placeholders) . "
            on conflict (id_tgroup, id_user)
                do update set
                    seq = EXCLUDED.seq
            ", ...$args
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->userId));
        return $affected;
    }
}
