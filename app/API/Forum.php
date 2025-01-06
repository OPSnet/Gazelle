<?php

namespace Gazelle\API;

class Forum extends AbstractAPI {
    public function run(): array {
        if (!isset($_GET['topic_id'])) {
            json_error('Missing topic id');
        }

        $forum = self::$db->rowAssoc("
            SELECT ft.ID,
                ft.Title,
                um.Username AS Author,
                f.Name AS Forum,
                f.MinClassRead
            FROM forums_topics AS ft
            INNER JOIN users_main AS um ON (um.ID = ft.AuthorID)
            INNER JOIN forums AS f ON (f.ID = ft.ForumID)
            WHERE ft.ID = ?
            ", $_GET['topic_id']
        );
        if (is_null($forum)) {
            json_error('Topic not found');
        }
        return $forum;
    }
}
