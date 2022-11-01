<?php

namespace Gazelle\Editor;

class UserBookmark extends \Gazelle\Base {
    protected const CACHE_KEY = 'bookmarks_group_ids_%d';

    public function __construct(
        protected readonly int $userId,
    ) {}

    /**
     * Uses (checkboxes) $_POST['remove'] to delete entries.
     */
    public function remove(array $groupIds): int {
        self::$db->prepared_query("
            DELETE FROM bookmarks_torrents
            WHERE UserID = ?
                AND GroupID IN (" . placeholders($groupIds) . ")
            ", $this->userId, ...$groupIds
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->userId));
        return self::$db->affected_rows();
    }

    /**
     * Uses $_POST['sort'] values to update entries.
     */
    public function modify(array $list): int {
        $placeholders = [];
        $args = [];
        foreach ($list as $groupId => $sequence) {
            if (is_number($sequence) && is_number($groupId)) {
                $placeholders[] = '(?, ?, ?)';
                $args = array_merge($args,  [$groupId, $sequence, $this->userId]);
            }
        }
        if (empty($placeholders)) {
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
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->userId));
        return self::$db->affected_rows();
    }
}
