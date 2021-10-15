<?php

namespace Gazelle;

/* Conversation thread, allows protected staff notes intermingled
 * with the public notes that both staff and member can see.
 * A collection of notes is called a story.
 */

class Thread extends BaseObject {
    protected $type;    // the type of thread
    protected $created; // date created
    protected $story;   // the array of notes in the conversation

    public function tableName(): string { return 'thread'; }
    public function url(): string { return ''; }
    public function link(): string { return ''; }
    public function flush() {}

    protected const CACHE_KEY = "thread_%d";
    protected const STORY_KEY = "thread_story_%d";

    public function __construct(int $id) {
        parent::__construct($id);
        $key = sprintf(self::CACHE_KEY, $this->id);
        [$this->type, $this->created] = $this->cache->get_value($key);
        if (is_null($this->type)) {
            [$this->type, $this->created] = $this->db->row("
                SELECT tt.Name as ThreadType,
                    t.Created
                FROM thread t
                INNER JOIN thread_type tt ON (tt.ID = t.ThreadTypeID)
                WHERE t.ID = ?
                ", $this->id
            );
            if (is_null($this->type)) {
                throw new Exception\ResourceNotFoundException($this->id);
            }
            $this->cache->cache_value($key, [$this->type, $this->created], 86400);
        }
        return $this->refresh(); /* load the story */
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
    public function saveNote(int $userId, string $body, string $visibility) {
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
    public function modifyNote(int $id, string $body, string $visibility) {
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
    public function removeNote(int $noteId) {
        $this->db->prepared_query("
            DELETE FROM thread_note
            WHERE ThreadID = ?
                AND ID = ?
            ", $this->id, $noteId
        );
        return $this->refresh();
    }

    /**
     * Refresh the story cache when a note is added, changed, deleted.
     */
    protected function refresh() {
        $this->db->prepared_query("
            SELECT tn.ID      AS id,
                tn.UserID     AS user_id,
                um.Username   AS username,
                tn.Visibility AS visibility,
                tn.Created    AS created,
                tn.Body       AS body
            FROM thread_note tn
            INNER JOIN users_main um ON (um.ID = tn.UserID)
            WHERE tn.ThreadID = ?
            ORDER BY tn.Created;
            ", $this->id
        );
        $this->story = $this->db->to_array('id', MYSQLI_ASSOC, false);
        $this->cache->cache_value(sprintf(self::STORY_KEY, $this->id), $this->story, 86400);
        return $this;
    }
}
