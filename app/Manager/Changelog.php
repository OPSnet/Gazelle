<?php

namespace Gazelle\Manager;

class Changelog extends \Gazelle\Base {
    public function create(string $message, string $author): int {
        self::$db->prepared_query("
            INSERT INTO changelog
                   (Message, Author)
            VALUES (?,       ?)
            ", trim($message), trim($author)
        );
        return self::$db->inserted_id();
    }

    public function remove(int $id): int {
        self::$db->prepared_query("
            DELETE FROM changelog WHERE ID = ?
            ", $id
        );
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
                Time     AS date
            FROM changelog
            ORDER BY Time DESC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }
}
