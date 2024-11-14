<?php

namespace Gazelle;

class UserNavigation extends BaseObject {
    final public const tableName = 'nav_items';
    final public const CACHE_KEY = 'unav_%d';

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        self::$cache->delete_value(Manager\UserNavigation::LIST_KEY);
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return "<a href=\"{$this->location()}\">User Link Editor</a>";
    }

    public function location(): string {
        return "tools.php?action=navigation";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT tag,
                    title,
                    target,
                    tests,
                    test_user,
                    mandatory,
                    initial
                FROM nav_items
                WHERE id = ?
                ", $this->id
            );
            self::$cache->cache_value($key, $info, 86400);
        }
        $this->info = $info;
        return $this->info;
    }

    public function isTestUser(): bool {
        return $this->info()['test_user'];
    }

    public function isMandatory(): bool {
        return $this->info()['mandatory'];
    }

    public function isInitial(): bool {
        return $this->info()['initial'];
    }

    public function tag(): string {
        return $this->info()['tag'];
    }

    public function target(): string {
        return $this->info()['target'];
    }

    public function tests(): string {
        return $this->info()['tests'];
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function modify(): bool {
        $success = parent::modify();
        if ($success) {
            self::$cache->delete_value(Manager\UserNavigation::LIST_KEY);
        }
        return $success;
    }

    public function remove(): int {
        $id = $this->id;
        $this->flush();
        self::$db->prepared_query("
            DELETE FROM nav_items WHERE id = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            self::$cache->delete_multi([
                sprintf(Manager\UserNavigation::ID_KEY, $id),
                Manager\UserNavigation::LIST_KEY,
            ]);
        }
        return $affected;
    }
}
