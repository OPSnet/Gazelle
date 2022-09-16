<?php

namespace Gazelle;

class ReportV2 extends BaseObject {

    protected int $moderatorId;
    protected int $groupId;
    protected int $torrentId;

    public function tableName(): string { return 'reportsv2'; }
    public function flush() {}

    public function url(): string {
        return "reportsv2.php?view=report&amp;id=" . $this->id;
    }

    public function link(): string {
        return sprintf('<a href="%s">Report #%d</a>', $this->url(), $this->id());
    }

    public function setModeratorId(int $moderatorId) {
        $this->moderatorId = $moderatorId;
        return $this;
    }

    public function setGroupId(int $groupId) {
        $this->groupId = $groupId;
        return $this;
    }

    public function setTorrentId(int $torrentId) {
        $this->torrentId = $torrentId;
        return $this;
    }

    public function setTorrentFlag(string $tableName): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO {$tableName}
                   (UserID, TorrentID)
            VALUES (?,      ?)
            ", $this->moderatorId, $this->torrentId
        );
        $affected = self::$db->affected_rows();
        (new TGroup($this->groupId))?->flush();
        return $affected;
    }

    public function claim(int $userId): bool {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Status = 'InProgress',
                ResolverID = ?
            WHERE ID = ?
            ", $userId, $this->id
        );
        return self::$db->affected_rows() === 1;
    }

    /**
     * Unclaim a report (make it new and clear the resolver)
     *
     * @return int 1 if unresolved, 0 if nothing changed and -1 if the ID does not match a report
     */
    public function unclaim(): int {
        if (self::$db->scalar("SELECT 1 FROM reportsv2 WHERE ID = ?", $this->id)) {
            self::$db->prepared_query("
                UPDATE reportsv2 SET
                    LastChangeTime = now(),
                    Status = 'New',
                    ResolverID = 0
                WHERE ResolverID != 0 AND ID = ?
                ", $this->id
            );
            return self::$db->affected_rows();
        }
        return -1;
    }

    public function resolve(string $message): bool {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ModComment = ?
            WHERE ID = ?
            ", $message, $this->id
        );
        self::$cache->decrement('num_torrent_reportsv2');
        return self::$db->affected_rows() === 1;
    }

    public function moderatorResolve(int $userId, string $message): bool {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ResolverID = ?,
                ModComment = ?
            WHERE Status != 'Resolved'
                AND ID = ?
            ", $userId, $message, $this->id
        );
        (new \Gazelle\Torrent($this->torrentId))->flush();
        return self::$db->affected_rows() > 0;
    }

    /**
     * Finalize a report: log the final details post-resolve
     */
    public function finalize(string $resolveType, string $log, string $message): bool {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Type = ?,
                LogMessage = ?,
                ModComment = ?
            WHERE ID = ?
            ", $resolveType, $log, $message, $this->id
        );
        return self::$db->affected_rows() === 1;
    }

    public function changeType(string $type): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Type = ?
            WHERE ID = ?
            ", $type, $this->id
        );
        return self::$db->affected_rows();
    }

    /**
     * Update the comment of a report
     */
    public function comment(string $comment): int {
        self::$db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                ModComment = ?
            WHERE ID = ?
            ", trim($comment), $this->id
        );
        return self::$db->affected_rows();
    }
}
