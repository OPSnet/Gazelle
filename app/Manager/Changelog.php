<?php

namespace Gazelle\Manager;

class Changelog extends \Gazelle\Base {
    public function create(string $message, string $author): int {
        $this->db->prepared_query("
            INSERT INTO changelog
                   (Message, Author)
            VALUES (?,       ?)
            ", trim($message), trim($author)
        );
        return $this->db->inserted_id();
    }

    public function remove(int $id): int {
        $this->db->prepared_query("
            DELETE FROM changelog WHERE ID = ?
            ", $id
        );
        return $this->db->affected_rows();
    }

    public function total(): int {
        return $this->db->scalar("
            SELECT count(*) FROM changelog
        ");
    }

    public function page(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT ID    AS id,
                Message  AS message,
                Author   AS author,
                Time     AS date
            FROM changelog
            ORDER BY Time DESC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return $this->db->to_array(false, MYSQLI_ASSOC);
    }
}
