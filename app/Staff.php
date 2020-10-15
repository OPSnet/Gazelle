<?php

namespace Gazelle;

use Permissions;
use Users;

class Staff extends Base {

    /** @var array */
    protected $user;

    public function __construct(array $user) {
        parent::__construct();
        $this->user = $user;
    }

    public function id() {
        return $this->user['ID'];
    }

    public function blogAlert() {
        if (($readTime = $this->cache->get_value('staff_blog_read_'. $this->user['ID'])) === false) {
            $readTime = $this->db->scalar('
                SELECT unix_timestamp(Time)
                FROM staff_blog_visits
                WHERE UserID = ?
                ', $this->user['ID']
            ) ?? 0;
            $this->cache->cache_value('staff_blog_read_' . $this->user['ID'], $readTime, 1209600);
        }
        if (($blogTime = $this->cache->get_value('staff_blog_latest_time')) === false) {
            $blogTime = $this->db->scalar('
                SELECT unix_timestamp(max(Time))
                FROM staff_blog
                '
            ) ?? 0;
            $this->cache->cache_value('staff_blog_latest_time', $blogTime, 1209600);
        }
        return $readTime < $blogTime;
    }

    public function pmCount() {
        $conditions = [
            "Status = 'Unanswered'",
            '(AssignedToUser = ? OR LEAST(?, Level) <= ?)',
        ];
        $params = [$this->user['ID'], Permissions::get_level_cap(), $this->user['EffectiveClass']];
        [$classes] = Users::get_classes();
        if (check_perms('users_mod')) {
            $conditions[] = 'Level >= ?';
            $params[] = $classes[MOD]['Level'];
        } elseif ($this->user['PermissionID'] === FORUM_MOD) {
            $conditions[] = 'Level >= ?';
            $params[] = $classes[FORUM_MOD]['Level'];
        }

        return $this->db->scalar("
            SELECT count(*)
            FROM staff_pm_conversations
            WHERE " . implode(' AND ', $conditions), ...$params);
    }
}
