<?php

namespace Gazelle;

class ReportV2 extends Base {

    protected $id;
    protected $moderatorId;
    protected $groupId;
    protected $torrentId;

    public function __construct(int $id) {
        $this->id = $id;
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
        self::$cache->delete_value("torrents_details_" . $this->groupId);
        return self::$db->affected_rows();
    }

    /**
     * Claim a report.
     *
     * @param int User ID
     * @return bool claim success
     */
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
     * @return 1 if unresolved, 0 if nothing changed and -1 if the ID does not match a report
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

    /**
     * Resolve a report
     *
     * @param string The resolve message
     * @return bool resolve success
     */
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

    /**
     * Moderator-resolve a report
     *
     * @param int User ID of moderator
     * @param string The resolve message
     * @return bool true if successfully resolved, false if nothing changed
     */
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
        return self::$db->affected_rows() > 0;
    }

    /**
     * Finalize a report: log the final details post-resolve
     *
     * @param int User ID of moderator
     * @param string The resolve message
     * @return bool true if successfully resolved, false if nothing changed
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

    /**
     * Change the type of a report
     *
     * @param string report type
     * @return 1 if successfully changed, 0 if nothing changed
     */
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
     *
     * @param string comment
     * @return 1 if successfully commented, 0 if nothing changed
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
