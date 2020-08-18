<?php

namespace Gazelle\Manager;

class ApplicantRole extends \Gazelle\Base {

    const CACHE_KEY_ALL       = 'approle_list_all';
    const CACHE_KEY_PUBLISHED = 'approle_list_published';

    public function create(string $title, string $description, bool $published, int $userId) {
        $this->db->prepared_query("
            INSERT INTO applicant_role
                   (Title, Description, Published, UserID)
            VALUES (?,     ?,           ?,         ?)
            ", $title, $description, $published, $userId
        );
        return new \Gazelle\ApplicantRole($this->db->inserted_id());
    }

    public function list($all = false) {
        $key = $all ? self::CACHE_KEY_ALL : self::CACHE_KEY_PUBLISHED;
        $list = $this->cache->get_value($key);
        if ($list === false) {
            $where = $all ? '/* all */' : 'WHERE r.Published = 1';
            $this->db->prepared_query("
                SELECT r.ID as role_id, r.Title as role, r.Published, r.Description, r.UserID, r.Created, r.Modified
                FROM applicant_role r
                $where
                ORDER BY r.Title
            ");
            $list = [];
            while (($row = $this->db->next_record(MYSQLI_ASSOC))) {
                $list[$row['role']] = [
                    'id'          => $row['role_id'],
                    'published'   => $row['Published'] ? 1 : 0,
                    'description' => $row['Description'],
                    'user_id'     => $row['UserID'],
                    'created'     => $row['Created'],
                    'modified'    => $row['Modified']
                ];
            }
            $this->cache->cache_value($key, $list, 86400 * 10);
        }
        return $list;
    }

    public function title(int $roleId) {
        $list = $this->list(true);
        foreach ($list as $role => $data) {
            if ($data['id'] == $roleId) {
                return $role;
            }
        }
        return null;
    }
}
