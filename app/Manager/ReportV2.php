<?php

namespace Gazelle\Manager;

class ReportV2 extends \Gazelle\Base {

    protected const ID_KEY = 'zz_r2_%d';

    protected array $categories = [
        'master' => 'General',
        '1' => 'Music',
        '2' => 'Application',
        '3' => 'E-Book',
        '4' => 'Audiobook',
        '5' => 'E-Learning Video',
        '6' => 'Comedy',
        '7' => 'Comics',
    ];

    protected array $types;
    protected $filter;

    public function createReport(string $viewerId, int $torrentID, string $type, string $userComment, string$tracks, string $images, string $extraIDs, string $links): void {
        self::$db->prepared_query("
            INSERT INTO reportsv2
                   (ReporterID, TorrentID, Type, UserComment, Track, Image, ExtraID, Link)
            VALUES (?,          ?,         ?,    ?,           ?,     ?,     ?,       ?)
            ", $viewerId, $torrentID, $type, $userComment, $tracks, $images, $extraIDs, $links
        );
    }

    public function findById(int $reportId): ?\Gazelle\ReportV2 {
        $key = sprintf(self::ID_KEY, $reportId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM reportsv2 WHERE ID = ?
                ", $reportId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\ReportV2($id) : null;
    }

    public function existsRecent(int $torrentId, int $ViewerId): bool {
        return (bool)self::$db->scalar("
            SELECT ID
            FROM reportsv2
            WHERE ReportedTime > now() - INTERVAL 5 SECOND
                AND TorrentID = ?
                AND ReporterID = ?
            ", $torrentId, $ViewerId);
    }

    public function types(): array {
        if (!isset($this->types)) {
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

    public function newSummary(): array {
        self::$db->prepared_query("
            SELECT Type  AS type,
                count(*) AS total
            FROM reportsv2
            WHERE Status = 'New'
            GROUP BY Type
            ORDER BY Type
        ");
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['title'] = $this->type($row['type'])['title'];
        }
        return $list;
    }

    protected function decorateUser(User $userMan, array $list): array {
        foreach ($list as &$row) {
            $row['user'] = $userMan->findById($row['user_id']);
        }
        unset($row);
        return $list;
    }

    public function inProgressSummary(User $userMan): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reportsv2 AS r
            WHERE r.Status = 'InProgress'
            GROUP BY r.ResolverID
            ORDER BY total DESC
        ");
        return $this->decorateUser($userMan, self::$db->to_array(false, MYSQLI_ASSOC, false));
    }

    public function resolvedSummary(User $userMan): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reportsv2 AS r
            WHERE r.ResolverID > 0
            GROUP BY r.ResolverID
            ORDER BY total DESC
        ");
        return $this->decorateUser($userMan, self::$db->to_array(false, MYSQLI_ASSOC, false));
    }

    protected function resolvedLastInterval(User $userMan, string $interval): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reportsv2 AS r
            WHERE r.ResolverID > 0
                AND  r.LastChangeTime > now() - INTERVAL $interval
            GROUP BY r.ResolverID
            ORDER BY total DESC
        ");
        return $this->decorateUser($userMan, self::$db->to_array(false, MYSQLI_ASSOC, false));
    }

    public function resolvedLastDay(User $userMan): array {
        return $this->resolvedLastInterval($userMan, '1 DAY');
    }

    public function resolvedLastWeek(User $userMan): array {
        return $this->resolvedLastInterval($userMan, '1 WEEK');
    }

    public function resolvedLastMonth(User $userMan): array {
        return $this->resolvedLastInterval($userMan, '1 MONTH');
    }

    /**
     * How many open reports exist for this group
     */
    public function totalReportsGroup(int $groupId): int {
        return self::$db->scalar("
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
     */
    public function totalReportsUploader(int $userId): int {
        return self::$db->scalar("
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

    protected function searchConfigure(): array {
        $cond = [];
        $args = [];
        $delcond = [];
        $delargs = [];
        if (isset($this->filter['reporter'])) {
            $cond[] = 'r.ReporterID = ?';
            $args[] = $this->filter['reporter']->id();
        }
        if (isset($this->filter['handler'])) {
            $cond[] = 'r.ResolverID = ?';
            $args[] = $this->filter['handler']->id();
        }
        if (isset($this->filter['uploader'])) {
            $userId = $this->filter['uploader']->id();
            $cond[] = 't.UserID = ?';
            $args[] = $userId;
            $delcond[] = '(dt.UserID IS NULL OR dt.UserID = ?)';
            $delargs[] = $userId;
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
        if (array_key_exists('group', $this->filter)) {
            $cond[] = 't.GroupID = ?';
            $args[] = $this->filter['group'];
            $delcond[] = '(dt.GroupID IS NULL OR dt.GroupID = ?)';
            $delargs[] = $this->filter['group'];
        }
        return [$cond, $args, $delcond, $delargs];
    }

    public function searchTotal(): int {
        [$cond, $args, $delcond, $delargs] = $this->searchConfigure();
        $where = (count($cond) == 0 && count($delcond) == 0)
            ? ''
            : ('WHERE ' . implode(" AND ", array_merge($cond, $delcond)));
        /* The construct below is pretty sick: we alias the group_log table to t
         * which means that t.GroupID in a condition refers to the same thing in
         * the `torrents` table as well. I am not certain this is entirely sane.
         */
        return self::$db->scalar("
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

    public function searchList(Torrent $torMan, User $userMan, int $limit, int $offset): array {
        [$cond, $args, $delcond, $delargs] = $this->searchConfigure();
        $where = (count($cond) == 0 && count($delcond) == 0)
            ? ''
            : ('WHERE ' . implode(" AND ", array_merge($cond, $delcond)));

        self::$db->prepared_query("
            SELECT r.ID,
                r.ReporterID AS reporter_id,
                r.ResolverID AS resolver_id,
                r.TorrentID  AS torrent_id,
                r.Type,
                r.ReportedTime,
                coalesce(t.UserID, dt.UserID)     AS uploader_id,
                coalesce(t.GroupID, dt.GroupID)   AS group_id,
                coalesce(t.Media, dt.Media)       AS media,
                coalesce(t.Format, dt.Format)     AS format,
                coalesce(t.Encoding, dt.Encoding) AS encoding
            FROM reportsv2 r
            LEFT JOIN torrents t          ON (t.ID = r.TorrentID)
            LEFT JOIN deleted_torrents dt ON (dt.ID = r.TorrentID)
            LEFT JOIN torrents_group g    ON (g.ID = t.GroupID)
            LEFT JOIN (
                SELECT max(t.ID) AS ID, t.TorrentID
                FROM group_log t
                INNER JOIN reportsv2 r using (TorrentID)
                WHERE " . implode(' AND ', array_merge(["t.Info NOT LIKE 'uploaded%'"], $cond)) . "
                GROUP BY t.TorrentID
            ) LASTLOG USING (TorrentID)
            LEFT JOIN group_log gl ON (gl.ID = LASTLOG.ID)
            $where
            ORDER BY r.ReportedTime DESC
            LIMIT ? OFFSET ?
            ", ...array_merge($args, $args, $delargs, [$limit, $offset])
        );

        $list = [];
        $cache = []; // Avoid looking up a user more than once
        $result = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($result as $r) {
            foreach (['reporter_id', 'resolver_id', 'uploader_id'] as $id) {
                if ($r[$id] && !isset($cache[$r[$id]])) {
                    $cache[$r[$id]] = $userMan->findById($r[$id]);
                }
            }
            $r['reporter'] = $cache[$r['reporter_id']];
            $r['resolver'] = $cache[$r['resolver_id']];
            $r['uploader'] = $cache[$r['uploader_id']] ?? null; // sometimes there is no uploader information
            $r['torrent']  = $torMan->findById($r['torrent_id']);
            $list[] = $r;
        }
        return $list;
    }
}
