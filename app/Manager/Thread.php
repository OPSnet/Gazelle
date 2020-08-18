<?php

namespace Gazelle\Manager;

/* Conversation thread, allows private staff notes intermingled
 * with the public notes that both staff and member can see.
 * A collection of notes is called a story.
 */

class Thread extends \Gazelle\Base {

    /**
     */
    public function createThread($type) {
        parent::__construct();
        $this->db->prepared_query("
            INSERT INTO thread (ThreadTypeID) VALUES (
                (SELECT ID FROM thread_type WHERE Name = ?)
            )
        ", $type);
        return new \Gazelle\Thread($this->db->inserted_id());
    }
}
