<?php

namespace Gazelle\Manager;

class ApplicantRole extends \Gazelle\Base {
    final protected const ID_KEY   = 'zz_applr_%d';
    final protected const LIST_KEY = 'approle';

    public function create(string $title, string $description, bool $published, \Gazelle\User $user): \Gazelle\ApplicantRole {
        self::$db->prepared_query("
            INSERT INTO applicant_role
                   (Title, Description, Published, UserID)
            VALUES (?,     ?,           ?,         ?)
            ", trim($title), trim($description), (int)$published, $user->id
        );
        $id = self::$db->inserted_id();
        $this->flush();
        return new \Gazelle\ApplicantRole($id);
    }

    public function findById(int $id): ?\Gazelle\ApplicantRole {
        $key = sprintf(self::ID_KEY, $id);
        $roleId = self::$cache->get_value($key);
        if ($roleId === false) {
            $roleId = self::$db->scalar("
                SELECT ID FROM applicant_role WHERE ID = ?
                ", $id
            );
            if (!is_null($roleId)) {
                self::$cache->cache_value($key, $roleId, 7200);
            }
        }
        return $roleId ? new \Gazelle\ApplicantRole($roleId) : null;
    }

    public function flush(): static {
        self::$cache->delete_value(self::LIST_KEY);
        return $this;
    }

    public function list(): array {
        $list = self::$cache->get_value(self::LIST_KEY);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT r.ID FROM applicant_role r ORDER BY r.Title
            ");
            $list = self::$db->collect(0);
            self::$cache->cache_value(self::LIST_KEY, $list, 0);
        }
        return array_map($this->findById(...), $list);
    }

    public function publishedList(): array {
        return array_filter($this->list(), fn($r) => $r->isPublished());
    }
}
