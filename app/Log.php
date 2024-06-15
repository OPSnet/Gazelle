<?php

namespace Gazelle;

use Gazelle\Intf\CategoryHasArtist;

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
    public function group(TGroup|CategoryHasArtist $tgroup, ?User $user, string $message): static {
        $qid = self::$db->get_query_id();
        self::$db->prepared_query("
            INSERT INTO group_log
                   (GroupID, UserID, Info, TorrentID, Hidden)
            VALUES (?,       ?,      ?,    0,         0)
            ", $tgroup->id(), $user?->id(), $message
        );
        self::$db->set_query_id($qid);
        return $this;
    }

    /**
     * Write a torrent entry
     */
    public function torrent(Torrent $torrent, ?User $user, string $message): static {
        $qid = self::$db->get_query_id();
        self::$db->prepared_query("
            INSERT INTO group_log
                   (GroupID, TorrentID, UserID, Info, Hidden)
            VALUES (?,       ?,         ?,      ?,    0)
            ", $torrent->groupId(), $torrent->id(), $user?->id(), $message
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
