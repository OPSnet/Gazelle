<?php

namespace Gazelle;

/* Conversation thread, allows protected staff notes intermingled
 * with the public notes that both staff and member can see.
 * A collection of notes is called a story.
 */

class Thread extends Base {
    protected $id;      // the ID of the row in the thread table
    protected $type;    // the type of thread
    protected $created; // date created
    protected $story;   // the array of notes in the conversation

    /**
     */
    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
        $key = "thread_$id";
        $data = $this->cache->get_value($key);
        if ($data === false) {
            $data = $this->db->row("
                SELECT tt.Name as ThreadType, t.Created
                FROM thread t
                INNER JOIN thread_type tt ON (tt.ID = t.ThreadTypeID)
                WHERE t.ID = ?
            ", $id);
            if (!$data) {
                throw new Exception\ResourceNotFoundException($id);
            }
            $data = $this->db->next_record();
            $this->cache->cache_value($key, $data, 86400);
        }
        [$this->type, $this->created] = $data;
        return $this->refresh(); /* load the story */
    }

    /**
     * @return int The id of a Thread
     */
    public function id() {
        return $this->id;
    }

    /**
     * Get the array of notes of the thread. A note is a hash with the following items:
     *  id         - the id in thread_note table
     *  user_id    - the id of the author in the users_main table
     *  user_name  - the name of the member (normally we don't need anything else from there).
     *  visibility - this note is 'public' or just visibible to 'staff'
     *  created    - when was this note created
     *  body       - the note text itself
     * @return array The list of notes in a thread ordered by most recent first.
     */
    public function story() {
        return $this->story;
    }

    /**
     * Persist a note to the db.
     * @param int $userId The note author
     * @param string $body The note text
     * @param int $visibility 'public' or 'staff'
     */
    public function saveNote($userId, $body, $visibility) {
        $this->db->prepared_query("
            INSERT INTO thread_note
                   (ThreadID, UserID, Body, Visibility)
            VALUES (?,        ?,      ?,    ?)
            ", $this->id, $userId, $body, $visibility
        );
        return $this->refresh();
    }

    /**
     * Persist a change to the note
     * @param int $id The id to identify a note
     * @param string $body The note text
     * @param int $visibility 'public' or 'staff'
     */
    public function modifyNote($id, $body, $visibility) {
        $this->db->prepared_query("
            UPDATE thread_note SET
                Body = ?,
                Visibility = ?
            WHERE ID = ?
            ", $body, $visibility, $id
        );
        return $this->refresh();
    }

    /**
     * Delete a note.
     * @param int $note_id The id to identify a note
     */
    public function removeNote($note_id) {
        $this->db->prepared_query("
            DELETE FROM thread_note
            WHERE ThreadID = ? AND ID = ?
            ", $this->id(), $note_id
        );
        return $this->refresh();
    }

    /**
     * Refresh the story cache when a note is added, changed, deleted.
     */
    protected function refresh() {
        $key = "thread_story_" . $this->id;
        $this->db->prepared_query("
            SELECT ID, UserID, Visibility, Created, Body
            FROM thread_note
            WHERE ThreadID = ?
            ORDER BY created;
        ", $this->id);
        $this->story = [];
        if ($this->db->has_results()) {
            $user_cache = [];
            while (($row = $this->db->next_record())) {
                if (!in_array($row['UserID'], $user_cache)) {
                    $user = \Users::user_info($row['UserID']);
                    $user_cache[$row['UserID']] = $user['Username'];
                }
                $this->story[] = [
                    'id'         => $row['ID'],
                    'user_id'    => $row['UserID'],
                    'user_name'  => $user_cache[$row['UserID']],
                    'visibility' => $row['Visibility'],
                    'created'    => $row['Created'],
                    'body'       => $row['Body']
                ];
            }
        }
        $this->cache->cache_value($key, $this->story, 3600);
        return $this;
    }
}
