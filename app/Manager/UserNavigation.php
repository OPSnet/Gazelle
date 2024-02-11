<?php

namespace Gazelle\Manager;

class UserNavigation extends \Gazelle\BaseManager {
    final public const LIST_KEY = 'unav_list';
    final public const ID_KEY = 'zz_unav_%d';

    /**
     * Create a forum control rule
     */
    public function create(
        string $tag,
        string $title,
        string $target,
        string $tests,
        bool   $testUser,
        bool   $mandatory,
        bool   $initial,
    ): \Gazelle\UserNavigation {
        self::$db->prepared_query("
            INSERT INTO nav_items
                   (tag, title, target, tests, test_user, mandatory, initial)
            VALUES (?,   ?,     ?,      ?,     ?,         ?,         ?)
            ", $tag, $title, $target, $tests, $testUser, $mandatory, $initial
        );
        $id = self::$db->inserted_id();
        self::$cache->delete_value(self::LIST_KEY);
        return new \Gazelle\UserNavigation($id);
    }

    public function findById(int $controlId): ?\Gazelle\UserNavigation {
        $key = sprintf(self::ID_KEY, $controlId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM forums_nav WHERE ID = ?
                ", $controlId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\UserNavigation($id) : null;
    }

    public function userControlList(\Gazelle\User $user): array {
        $navList = $user->navigationList();
        $list = [];
        foreach ($this->fullList() as $n) {
            if (($n['mandatory'] || in_array($n['id'], $navList)) || (!count($navList) && $n['initial'])) {
                $list[] = $n;
            }
        }
        return $list;
    }

    public function fullList(): array {
        $list = self::$cache->get_value(self::LIST_KEY);
        if (!$list) {
            self::$db->prepared_query("
                SELECT
                    id,
                    tag,
                    title,
                    target,
                    tests,
                    test_user,
                    mandatory,
                    initial
                FROM nav_items
            ");
            $list = self::$db->to_array("id", MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::LIST_KEY, $list, 0);
        }
        return $list;
    }
}
