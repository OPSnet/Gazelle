<?php

namespace Gazelle\Manager;

class ReportV2 extends \Gazelle\Base {

    protected $categories = [
        'master' => 'General',
        '1' => 'Music',
        '2' => 'Application',
        '3' => 'E-Book',
        '4' => 'Audiobook',
        '5' => 'E-Learning Video',
        '6' => 'Comedy',
        '7' => 'Comics',
    ];

    protected $types;
    protected $filter;
    protected $userMan;

    public function types(): array {
        if (!$this->types) {
            $this->types = require('ReportV2Types.php');
        }
        return $this->types;
    }

    public function categories(): array {
        return $this->categories;
    }

    public function type(string $type): array {
        $types = $this->types();
        if (array_key_exists($type, $types['master'])) {
            return $types['master'][$type];
        }
        foreach ($this->categories as $category => $name) {
            if (array_key_exists($type, $types[$category])) {
                return $types[$category][$type];
            }
        }
        return $this->categories['master']['other'];
    }

    public function typeName(string $type): string {
        $types = $this->types();
        if (array_key_exists($type, $types['master'])) {
            return $this->categories['master'] . ' &rsaquo; ' . $types['master'][$type]['title'];
        }
        foreach ($this->categories as $category => $name) {
            if (array_key_exists($type, $types[$category])) {
                return $name . ' &rsaquo; ' . $types[$category][$type]['title'];
            }
        }
        return $this->categories['master']['other']['title'];
    }

    public function inProgressSummary(): array {
        $this->db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS nr
            FROM reportsv2 AS r
            WHERE r.Status = 'InProgress'
            GROUP BY r.ResolverID
            ORDER By nr DESC
        ");
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function newSummary(): array {
        $this->db->prepared_query("
            SELECT Type,
                count(*) AS Count
            FROM reportsv2
            WHERE Status = 'New'
            GROUP BY Type
            ORDER BY Type
        ");
        return $this->db->to_array();
    }

    public function resolvedSummary(): array {
        $this->db->prepared_query("
            SELECT r.ResolverID,
                um.Username,
                count(*) AS Reports
            FROM reportsv2 AS r
            INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
            GROUP BY r.ResolverID
            ORDER BY Reports DESC
        ");
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    protected function resolvedLastInterval(string $interval): array {
        $this->db->prepared_query("
            SELECT r.ResolverID,
                um.Username,
                count(*) AS Reports
            FROM reportsv2 AS r
            INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
            WHERE r.LastChangeTime > now() - INTERVAL $interval
            GROUP BY r.ResolverID
            ORDER BY Reports DESC
        ");
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    public function resolvedLastDay(): array {
        return $this->resolvedLastInterval('1 DAY');
    }

    public function resolvedLastWeek(): array {
        return $this->resolvedLastInterval('1 WEEK');
    }

    public function resolvedLastMonth(): array {
        return $this->resolvedLastInterval('1 MONTH');
    }

    /**
     * How many open reports exist for this group
     *
     * @param int Group ID
     * @return number of reports
     */
    public function totalReportsGroup(int $groupId): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.GroupID = ?
            ", $groupId
        );
    }

    /**
     * How many open reports exist for this uploader
     *
     * @param int User ID of uploader
     * @return number of reports
     */
    public function totalReportsUploader(int $userId): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.UserID = ?
            ", $userId
        );
    }

    public function setSearchFilter(array $filter) {
        $this->filter = $filter;
        return $this;
    }

    public function setUserManager(User $userMan) {
        $this->userMan = $userMan;
        return $this;
    }

    protected function searchConfigure(): array {
        $cond = [];
        $args = [];
        $delcond = [];
        $delargs = [];
        if (array_key_exists('reporter', $this->filter) && $this->filter['reporter']) {
            $user = $this->userMan->findByUsername($this->filter['reporter']);
            if (is_null($user)) {
                throw new \Gazelle\Exception\ResourceNotFoundException("reporter '{$this->filter['reporter']}'");
            }
            $cond[] = 'r.ReporterID = ?';
            $args[] = $user->id();
        }
        if (array_key_exists('handler', $this->filter) && $this->filter['handler']) {
            $user = $this->userMan->findByUsername($this->filter['handler']);
            if (is_null($user)) {
                throw new \Gazelle\Exception\ResourceNotFoundException("handler '{$this->filter['handler']}'");
            }
            $cond[] = 'r.ResolverID = ?';
            $args[] = $user->id();
        }
        if (array_key_exists('report-type', $this->filter)) {
            $cond[] = 'r.Type in (' . placeholders($this->filter['report-type']) . ')';
            $args = array_merge($args, $this->filter['report-type']);
        }
        if (array_key_exists('dt-from', $this->filter)) {
            $cond[] = 'r.ReportedTime >= ?';
            $args[] = $this->filter['dt-from'];
        }
        if (array_key_exists('dt-until', $this->filter)) {
            $delcond[] = 'r.ReportedTime <= ? + INTERVAL 1 DAY';
            $delargs[] = $this->filter['dt-until'];
        }
        if (array_key_exists('torrent', $this->filter)) {
            $delcond[] = 'r.TorrentID = ?';
            $delargs[] = $this->filter['torrent'];
        }
        if (array_key_exists('uploader', $this->filter) && $this->filter['uploader']) {
            $user = $this->userMan->findByUsername($this->filter['uploader']);
            if (is_null($user)) {
                throw new \Gazelle\Exception\ResourceNotFoundException("uploader '{$this->filter['uploader']}'");
            }
            $userId = $user->id();
            $cond[] = 't.UserID = ?';
            $args[] = $userId;
            $delcond[] = '(dt.UserID IS NULL OR dt.UserID = ?)';
            $delargs[] = $userId;
        }
        if (array_key_exists('group', $this->filter)) {
            $cond[] = 't.GroupID = ?';
            $args[] = $this->filter['group'];
            $delcond[] = '(dt.GroupID IS NULL OR dt.GroupID = ?)';
            $delargs[] = $this->filter['group'];
        }
        return [$cond, $args, $delcond, $delargs];
    }

    public function searchTotal(): int {
        [$cond, $args, $delcond, $delargs] = $this->searchConfigure();;
        $where = (count($cond) == 0 && count($delcond) == 0)
            ? ''
            : ('WHERE ' . implode(" AND ", array_merge($cond, $delcond)));
        /* The construct below is pretty sick: we alias the group_log table to t
         * which means that t.GroupID in a condition refers to the same thing in
         * the `torrents` table as well. I am not certain this is entirely sane.
         */
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 r
            LEFT JOIN torrents t ON (t.ID = r.TorrentID)
            LEFT JOIN deleted_torrents dt ON (dt.ID = r.TorrentID)
            LEFT JOIN torrents_group g on (g.ID = t.GroupID)
            LEFT JOIN (
                SELECT max(t.ID) AS ID, t.TorrentID
                FROM group_log t
                INNER JOIN reportsv2 r using (TorrentID)
                WHERE " . implode(' AND ', array_merge(["t.Info NOT LIKE 'uploaded%'"], $cond)) . "
                GROUP BY t.TorrentID
            ) LASTLOG USING (TorrentID)
            LEFT JOIN group_log gl ON (gl.ID = LASTLOG.ID)
            $where
            ", ...array_merge($args, $args, $delargs)
        );
    }

    public function searchPage(int $limit, int $offset): array {
        [$cond, $args, $delcond, $delargs] = $this->searchConfigure();;
        $where = (count($cond) == 0 && count($delcond) == 0)
            ? ''
            : ('WHERE ' . implode(" AND ", array_merge($cond, $delcond)));
        $this->db->prepared_query("
            SELECT r.ID,
                r.ReporterID,
                reporter.Username AS reporter_username,
                r.ResolverID,
                resolver.Username AS resolver_username,
                r.TorrentID,
                coalesce(t.UserID, dt.UserID) AS UserID,
                coalesce(uploader.Username, del_uploader.Username) AS uploader_username,
                coalesce(t.GroupID, dt.GroupID)   AS GroupID,
                coalesce(t.Media, dt.Media)       AS Media,
                coalesce(t.Format, dt.Format)     AS Format,
                coalesce(t.Encoding, dt.Encoding) AS Encoding,
                coalesce(g.Name, gl.Info)         AS Name,
                g.Year,
                r.Type,
                r.ReportedTime
            FROM reportsv2 r
            LEFT JOIN torrents t ON (t.ID = r.TorrentID)
            LEFT JOIN deleted_torrents dt ON (dt.ID = r.TorrentID)
            LEFT JOIN torrents_group g on (g.ID = t.GroupID)
            LEFT JOIN (
                SELECT max(t.ID) AS ID, t.TorrentID
                FROM group_log t
                INNER JOIN reportsv2 r using (TorrentID)
                WHERE " . implode(' AND ', array_merge(["t.Info NOT LIKE 'uploaded%'"], $cond)) . "
                GROUP BY t.TorrentID
            ) LASTLOG USING (TorrentID)
            LEFT JOIN group_log gl ON (gl.ID = LASTLOG.ID)
            LEFT JOIN users_main reporter ON (reporter.ID = r.ReporterID)
            LEFT JOIN users_main resolver ON (resolver.ID = r.ResolverID)
            LEFT JOIN users_main uploader ON (uploader.ID = t.UserID)
            LEFT JOIN users_main del_uploader ON (del_uploader.ID = dt.UserID)
            $where
            ORDER BY r.ReportedTime DESC
            LIMIT ? OFFSET ?
            ", ...array_merge($args, $args, $delargs, [$limit, $offset])
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }
}
