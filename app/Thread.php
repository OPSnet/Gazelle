<?php

namespace Gazelle;

/**
 * Conversation thread, allows protected staff notes intermingled
 * with the public notes that both staff and member can see.
 * A collection of notes is called a story.
 */

class Thread extends BaseObject {
    final public const tableName     = 'thread';
    protected const CACHE_KEY = "threadv2_%d";

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        unset($this->info);
        return $this;
    }
    public function link(): string { return ''; }
    public function location(): string { return ''; }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT tt.Name as type,
                    t.Created as created
                FROM thread t
                INNER JOIN thread_type tt ON (tt.ID = t.ThreadTypeID)
                WHERE t.ID = ?
                ", $this->id
            );
            self::$db->prepared_query("
                    SELECT n.ID AS id,
                        n.UserID     AS user_id,
                        n.Visibility AS visibility,
                        n.Created    AS created,
                        n.Body       AS body
                    FROM thread_note n
                    WHERE n.ThreadID = ?
                ", $this->id
            );
            $info['story'] = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $this->info;
    }

    public function created(): string {
        return $this->info()['created'];
    }

    /**
     * The user_id of the last user who replied to the thread. Useful to distinguish
     * whether staff made the most recent reply.
     *
     * @return int user_id or null if the thread is empty
     */
    public function lastNoteUserId(): ?int {
        $story = $this->story();
        if (!$story) {
            return null;
        }
        $last = end($story);
        return $last['user_id'];
    }

    /**
     * The modified date of a thread is the date of the latest note,
     * otherwise the date the thread was created.
     */
    public function modified(): string {
        $story = $this->story();
        if (!$story) {
            return $this->created();
        }
        $last = end($story);
        return $last['created'];
    }

    /**
     * The thread story itself, an array of [id, user_id, visibility, created, body]
     */
    public function story(): array {
        return $this->info()['story'];
    }

    public function type(): string {
        return $this->info()['type'];
    }

    /**
     * Persist a note to the db.
     */
    public function saveNote(User $user, string $body, string $visibility): int {
        self::$db->prepared_query("
            INSERT INTO thread_note
                   (ThreadID, UserID, Body, Visibility)
            VALUES (?,        ?,      ?,    ?)
            ", $this->id, $user->id(), $body, $visibility
        );
        $inserted = self::$db->inserted_id();
        $this->flush();
        return $inserted;
    }

    /**
     * Persist a change to the note
     */
    public function modifyNote(int $noteId, string $body, string $visibility): static {
        self::$db->prepared_query("
            UPDATE thread_note SET
                Body = ?,
                Visibility = ?
            WHERE ID = ?
            ", $body, $visibility, $noteId
        );
        return $this->flush();
    }

    public function removeNote(int $noteId): int {
        self::$db->prepared_query("
            DELETE FROM thread_note
            WHERE ThreadID = ?
                AND ID = ?
            ", $this->id, $noteId
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }
}
