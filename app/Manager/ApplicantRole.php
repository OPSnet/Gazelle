<?php

namespace Gazelle\Manager;

class ApplicantRole extends \Gazelle\Base {

    const CACHE_KEY_ALL       = 'approle_list_all';
    const CACHE_KEY_PUBLISHED = 'approle_list_published';

    public function create(string $title, string $description, bool $published, int $userId) {
        self::$db->prepared_query("
            INSERT INTO applicant_role
                   (Title, Description, Published, UserID)
            VALUES (?,     ?,           ?,         ?)
            ", $title, $description, $published ? 1 : 0, $userId
        );
        return new \Gazelle\ApplicantRole(self::$db->inserted_id());
    }

    public function list($all = false) {
        $key = $all ? self::CACHE_KEY_ALL : self::CACHE_KEY_PUBLISHED;
        $list = self::$cache->get_value($key);
        if ($list === false) {
            $where = $all ? '/* all */' : 'WHERE r.Published = 1';
            self::$db->prepared_query("
                SELECT r.ID       AS role_id,
                    r.Title       AS title,
                    r.Published   AS published,
                    r.Description AS description,
                    r.UserID      AS user_id,
                    r.Created     AS created,
                    r.Modified    AS modified
                FROM applicant_role r
                $where
                ORDER BY r.Title
            ");
            $list = self::$db->to_array('role_id', MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $list, 86400 * 10);
        }
        return $list;
    }

    public function title(int $roleId): ?string {
        $role = array_filter($this->list(true), function ($r) use ($roleId) { return $r['role_id'] == $roleId;});
        return current($role)['title'] ?? null;
    }
}
