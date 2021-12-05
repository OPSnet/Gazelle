<?php

namespace Gazelle\API;

class Wiki extends AbstractAPI {
    public function run() {
        if (!isset($_GET['wiki_id'])) {
            json_error('Missing wiki article id');
        }

        self::$db->prepared_query("
            SELECT
                wa.Title,
                wa.MinClassRead,
                um.Username AS Author,
                wa.Date
            FROM
                wiki_articles AS wa
                INNER JOIN users_main AS um ON um.ID = wa.Author
            WHERE
                wa.ID = ?", $_GET['wiki_id']);
        if (!self::$db->has_results()) {
            json_error('Wiki article not found');
        }
        $article = self::$db->next_record(MYSQLI_ASSOC, false);
        return $article;
    }
}
