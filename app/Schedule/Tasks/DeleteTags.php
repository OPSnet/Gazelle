<?php

namespace Gazelle\Schedule\Tasks;

class DeleteTags extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
            DELETE FROM torrents_tags
            WHERE NegativeVotes > 1
                AND NegativeVotes > PositiveVotes
            "
        );
        $this->processed = $this->db->affected_rows();
    }
}
