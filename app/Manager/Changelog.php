<?php

namespace Gazelle\Manager;

class Changelog extends \Gazelle\Base {
    protected const CACHE_KEY = 'changelog2';

    public function flush(): Changelog {
        self::$cache->delete_value(self::CACHE_KEY);
        return $this;
    }

    public function create(string $message, string $author): int {
        self::$db->prepared_query("
            INSERT INTO changelog
                   (Message, Author)
            VALUES (?,       ?)
            ", trim($message), trim($author)
        );
        $this->flush();
        return self::$db->inserted_id();
    }

    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM changelog WHERE ID = ?
            ", $id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function total(): int {
        return self::$db->scalar("
            SELECT count(*) FROM changelog
        ");
    }

    public function page(int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT ID    AS id,
                Message  AS message,
                Author   AS author,
                Time     AS created
            FROM changelog
            ORDER BY Time DESC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function headlines(): array {
        $list = self::$cache->get_value(self::CACHE_KEY);
        if ($list === false) {
            $list = $this->page(20, 0);
            self::$cache->cache_value(self::CACHE_KEY, $list, 0);
        }
        return $list;
    }
}
