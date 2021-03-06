<?php

namespace Gazelle\Editor;

class UserBookmark extends \Gazelle\Base {

    protected const CACHE_KEY = 'bookmarks_group_ids_%d';

    /**
     * The user ID
     * @var int $userId
     */
    protected $userId;

    public function __construct(int $userId) {
        parent::__construct();
        $this->userId = $userId;
    }

    /**
     * Uses (checkboxes) $_POST['remove'] to delete entries.
     *
     * @param array List of group IDs
     * @return int number of items removed
     */
    public function remove(array $groupIds): int {
        $this->db->prepared_query("
            DELETE FROM bookmarks_torrents
            WHERE UserID = ?
                AND GroupID IN (" . placeholders($groupIds) . ")
            ", $this->userId, ...$groupIds
        );
        $this->cache->delete_value(sprintf(self::CACHE_KEY, $this->userId));
        return $this->db->affected_rows();
    }

    /**
     * Uses $_POST['sort'] values to update entries.
     *
     * @param array List of group IDs
     * @return int number of items modified
     */
    public function modify(array $list) {
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
        $this->db->prepared_query("
            INSERT INTO bookmarks_torrents
                (GroupID, Sort, UserID)
            VALUES " . implode(', ', $placeholders) . "
            ON DUPLICATE KEY UPDATE
                Sort = VALUES (Sort)
            ", ...$args
        );
        $this->cache->delete_value(sprintf(self::CACHE_KEY, $this->userId));
        return $this->db->affected_rows();
    }
}
