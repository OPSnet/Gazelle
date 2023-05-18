<?php

namespace Gazelle\Task;

class DeleteTags extends \Gazelle\Task {
    public function run(): void {
        self::$db->prepared_query("
            DELETE FROM torrents_tags
            WHERE NegativeVotes > 1
                AND NegativeVotes > PositiveVotes
        ");
        $this->processed = self::$db->affected_rows();
    }
}
