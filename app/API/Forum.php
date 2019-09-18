<?php

namespace Gazelle\API;

class Forum extends AbstractAPI {
    private $fid = null;
    private $tid = null;

    public function run() {
        if (!isset($_GET['topic_id'])) {
            json_error('Missing topic id');
        }

        $this->db->prepared_query("
            SELECT
                ft.ID,
                ft.Title,
                um.Username AS Author,
                f.Name AS Forum,
                f.MinClassRead
            FROM
                forums_topics AS ft
                INNER JOIN users_main AS um ON um.ID = ft.AuthorID
                INNER JOIN forums AS f ON f.ID = ft.ForumID
            WHERE
                ft.ID = ?", $_GET['topic_id']);
        if (!$this->db->has_results()) {
            json_error('Topic not found');
        }
        $thread = $this->db->next_record(MYSQLI_ASSOC, false);
        return $thread;
    }
}
