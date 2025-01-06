<?php

namespace Gazelle\API;

class Wiki extends AbstractAPI {
    public function run(): array {
        if (!isset($_GET['wiki_id'])) {
            json_error('Missing wiki article id');
        }

        $article = self::$db->rowAssoc("
            SELECT wa.Title,
                wa.MinClassRead,
                um.Username AS Author,
                wa.Date
            FROM wiki_articles AS wa
            INNER JOIN users_main AS um ON (um.ID = wa.Author)
            WHERE wa.ID = ?
            ", $_GET['wiki_id']
        );
        if (is_null($article)) {
            json_error('Wiki article not found');
        }
        return $article;
    }
}
