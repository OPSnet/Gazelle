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

    public function flush(): Thread { return $this; }
    public function link(): string { return ''; }
    public function location(): string { return ''; }
    public function tableName(): string { return 'thread'; }

    protected const CACHE_KEY = "thread_%d";
    protected const STORY_KEY = "thread_story_%d";

    public function __construct(int $id) {
        parent::__construct($id);
        $key = sprintf(self::CACHE_KEY, $this->id);
        [$this->type, $this->created] = self::$cache->get_value($key);
        if (is_null($this->type)) {
            [$this->type, $this->created] = self::$db->row("
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
            self::$cache->cache_value($key, [$this->type, $this->created], 86400);
        }
        $this->refresh(); /* load the story */
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
    public function story(): array {
        return $this->story;
    }

    /**
     * Persist a note to the db.
     */
    public function saveNote(int $userId, string $body, string $visibility) {
        self::$db->prepared_query("
            INSERT INTO thread_note
                   (ThreadID, UserID, Body, Visibility)
            VALUES (?,        ?,      ?,    ?)
            ", $this->id, $userId, $body, $visibility
        );
        return $this->refresh();
    }

    /**
     * Persist a change to the note
     */
    public function modifyNote(int $id, string $body, string $visibility) {
        self::$db->prepared_query("
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
     */
    public function removeNote(int $noteId) {
        self::$db->prepared_query("
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
        self::$db->prepared_query("
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
        $this->story = self::$db->to_array('id', MYSQLI_ASSOC, false);
        self::$cache->cache_value(sprintf(self::STORY_KEY, $this->id), $this->story, 86400);
        return $this;
    }
}
