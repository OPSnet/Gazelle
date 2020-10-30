<?php

namespace Gazelle;

class ReportV2 extends Base {

    protected $id;

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
    }

    /**
     * Claim a report.
     *
     * @param int User ID
     * @return bool claim success
     */
    public function claim(int $userId): bool {
        $this->db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Status = 'InProgress',
                ResolverID = ?
            WHERE ID = ?
            ", $userId, $this->id
        );
        return $this->db->affected_rows() === 1;
    }

    /**
     * Unclaim a report (make it new and clear the resolver)
     *
     * @return 1 if unresolved, 0 if nothing changed and -1 if the ID does not match a report
     */
    public function unclaim(): int {
        if ($this->db->scalar("SELECT 1 FROM reportsv2 WHERE ID = ?", $this->id)) {
            $this->db->prepared_query("
                UPDATE reportsv2 SET
                    LastChangeTime = now(),
                    Status = 'New',
                    ResolverID = 0
                WHERE ResolverID != 0 AND ID = ?
                ", $this->id
            );
            return $this->db->affected_rows();
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
        $this->db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ModComment = ?
            WHERE ID = ?
            ", $message, $this->id
        );
        $this->cache->decrement('num_torrent_reportsv2');
        return $this->db->affected_rows() === 1;
    }

    /**
     * Moderator-resolve a report
     *
     * @param int User ID of moderator
     * @param string The resolve message
     * @return bool true if successfully resolved, false if nothing changed
     */
    public function moderatorResolve(int $userId, string $message): bool {
        $this->db->prepared_query("
            UPDATE reportsv2 SET
                Status = 'Resolved',
                LastChangeTime = now(),
                ResolverID = ?,
                ModComment = ?
            WHERE Status != 'Resolved'
                AND ID = ?
            ", $userId, $message, $this->id
        );
        return $this->db->affected_rows() > 0;
    }

    /**
     * Finalize a report: log the final details post-resolve
     *
     * @param int User ID of moderator
     * @param string The resolve message
     * @return bool true if successfully resolved, false if nothing changed
     */
    public function finalize(string $resolveType, string $log, string $message): bool {
        $this->db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Type = ?,
                LogMessage = ?,
                ModComment = ?
            WHERE ID = ?
            ", $resolveType, $log, $message, $this->id
        );
        return $this->db->affected_rows() === 1;
    }

    /**
     * Change the type of a report
     *
     * @param string report type
     * @return 1 if successfully changed, 0 if nothing changed
     */
    public function changeType(string $type): int {
        $this->db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                Type = ?
            WHERE ID = ?
            ", $type, $this->id
        );
        return $this->db->affected_rows();
    }

    /**
     * Update the comment of a report
     *
     * @param string comment
     * @return 1 if successfully commented, 0 if nothing changed
     */
    public function comment(string $comment): int {
        $this->db->prepared_query("
            UPDATE reportsv2 SET
                LastChangeTime = now(),
                ModComment = ?
            WHERE ID = ?
            ", trim($comment), $this->id
        );
        return $this->db->affected_rows();
    }
}
