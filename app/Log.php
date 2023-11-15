<?php

namespace Gazelle;

class Log extends Base {
    /**
     * Write a general message to the system log.
     */
    public function general(string $message): static {
        $qid = self::$db->get_query_id();
        self::$db->prepared_query("
            INSERT INTO log (Message) VALUES (?)
            ", mb_substr(trim($message), 0, 800)
        );
        self::$db->set_query_id($qid);
        return $this;
    }

    /**
     * Write a group entry
     */
    public function group(int $groupId, int $userId, string $message): static {
        $qid = self::$db->get_query_id();
        self::$db->prepared_query("
            INSERT INTO group_log
                   (GroupID, UserID, Info, TorrentID, Hidden)
            VALUES (?,       ?,      ?,    0,         0)
            ", $groupId, $userId, $message
        );
        self::$db->set_query_id($qid);
        return $this;
    }

    /**
     * Write a torrent entry
     */
    public function torrent(int $groupId, int $torrentId, ?int $userId, string $message): static {
        $qid = self::$db->get_query_id();
        self::$db->prepared_query("
            INSERT INTO group_log
                   (GroupID, TorrentID, UserID, Info, Hidden)
            VALUES (?,       ?,         ?,      ?,    0)
            ", $groupId, $torrentId, $userId, $message
        );
        self::$db->set_query_id($qid);
        return $this;
    }

    public function merge(\Gazelle\TGroup $old, \Gazelle\TGroup $new): int {
        self::$db->prepared_query("
            UPDATE group_log SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        return self::$db->affected_rows();
    }
}
