<?php

namespace Gazelle;

class Log extends Base {
    /**
     * Write a general message to the system log.
     *
     * @param string $message the message to write.
     */
    public function general(string $message) {
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
     *
     * @param string $message
     */
    public function group(int $groupId, int $userId, $message) {
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
     *
     * @param string $message
     */
    public function torrent(int $groupId, int $torrentId, int $userId, $message) {
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

    public function merge(int $oldId, int $newId): int {
        self::$db->prepared_query("
            UPDATE group_log SET
                GroupID = ?
            WHERE GroupID = ?
            ", $newId, $oldId
        );
        return self::$db->affected_rows();
    }
}
