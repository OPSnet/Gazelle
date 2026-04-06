<?php

namespace Gazelle\Manager\Torrent;

class Report extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_tr_%d';

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

    protected array $filter;

    public function __construct(
        protected \Gazelle\Manager\Torrent $torMan = new \Gazelle\Manager\Torrent(),
    ) {}

    public function create(
        \Gazelle\Torrent            $torrent,
        \Gazelle\User               $user,
        \Gazelle\Torrent\ReportType $reportType,
        \Gazelle\Util\Irc           $irc,
        string $reason,
        string $otherIdList,
        string $track = '',
        string $image = '',
        string $link  = '',
    ): \Gazelle\Torrent\Report {
        self::$db->prepared_query("
            INSERT INTO reportsv2
                   (ReporterID, TorrentID, Type, UserComment, ExtraID, Track, Image, Link)
            VALUES (?,          ?,         ?,    ?,           ?,     ?,     ?,       ?)
            ", $user->id, $torrent->id, $reportType->type(), $reason, $otherIdList, $track, $image, $link
        );

        $report = new \Gazelle\Torrent\Report(self::$db->inserted_id(), $this->torMan);
        if ($reportType->type() == 'urgent') {
            $irc::sendMessage(
                IRC_CHAN_MOD,
                "URGENT: {$user->username()} reported {$torrent->name()} – " . SITE_URL . $report->location()
            );
        }

        self::$cache->delete_value(sprintf(\Gazelle\TorrentAbstract::CACHE_REPORTLIST, $torrent->id));
        self::$cache->increment('num_torrent_reportsv2');
        $torrent->flush();

        return $report;
    }

    public function findById(int $id): ?\Gazelle\Torrent\Report {
        $key = sprintf(self::ID_KEY, $id);
        $reportId = self::$cache->get_value($key);
        if ($reportId === false) {
            $reportId = (int)self::$db->scalar("
                SELECT ID FROM reportsv2 WHERE ID = ?
                ", $id
            );
            if ($reportId) {
                self::$cache->cache_value($key, $reportId, 7200);
            }
        }
        return $reportId
            ? new \Gazelle\Torrent\Report($reportId, $this->torMan)
            : null;
    }

    public function findNewest(): ?\Gazelle\Torrent\Report {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT ID
                FROM reportsv2
                WHERE Status = 'New'
                ORDER BY ReportedTime DESC
                LIMIT 1
            ")
        );
    }

    public function existsRecent(\Gazelle\Torrent $torrent, \Gazelle\User $user): bool {
        return (bool)self::$db->scalar("
            SELECT ID
            FROM reportsv2
            WHERE ReportedTime > now() - INTERVAL 5 SECOND
                AND TorrentID = ?
                AND ReporterID = ?
            ", $torrent->id, $user->id);
    }

    public function categories(): array {
        return $this->categories;
    }

    public function newSummary(ReportType $reportTypeMan = new ReportType()): array {
        self::$db->prepared_query("
            SELECT Type  AS type,
                count(*) AS total
            FROM reportsv2
            WHERE Status = 'New'
            GROUP BY Type
            ORDER BY Type
        ");
        $list = self::$db->to_array(false, MYSQLI_ASSOC);
        foreach ($list as &$row) {
            $row['name'] = $reportTypeMan->findByType($row['type'])->name();
        }
        return $list;
    }

    protected function decorateUser(\Gazelle\Manager\User $userMan, array $list): array {
        foreach ($list as &$row) {
            $row['user'] = $userMan->findById($row['user_id']);
        }
        unset($row);
        return $list;
    }

    public function inProgressSummary(\Gazelle\Manager\User $userMan = new \Gazelle\Manager\User()): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reportsv2 AS r
            WHERE r.Status = 'InProgress'
            GROUP BY r.ResolverID
            ORDER BY total DESC
        ");
        return $this->decorateUser($userMan, self::$db->to_array(false, MYSQLI_ASSOC));
    }

    public function resolvedSummary(\Gazelle\Manager\User $userMan = new \Gazelle\Manager\User()): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reportsv2 AS r
            WHERE r.ResolverID > 0
            GROUP BY r.ResolverID
            ORDER BY total DESC
        ");
        return $this->decorateUser($userMan, self::$db->to_array(false, MYSQLI_ASSOC));
    }

    protected function resolvedLastInterval(\Gazelle\Manager\User $userMan, string $interval): array {
        self::$db->prepared_query("
            SELECT r.ResolverID AS user_id,
                count(*)        AS total
            FROM reportsv2 AS r
            WHERE r.ResolverID > 0
                AND r.LastChangeTime > now() - INTERVAL $interval
            GROUP BY r.ResolverID
            ORDER BY total DESC
        ");
        return $this->decorateUser($userMan, self::$db->to_array(false, MYSQLI_ASSOC));
    }

    public function resolvedLastDay(\Gazelle\Manager\User $userMan = new \Gazelle\Manager\User()): array {
        return $this->resolvedLastInterval($userMan, '1 DAY');
    }

    public function resolvedLastWeek(\Gazelle\Manager\User $userMan = new \Gazelle\Manager\User()): array {
        return $this->resolvedLastInterval($userMan, '1 WEEK');
    }

    public function resolvedLastMonth(\Gazelle\Manager\User $userMan = new \Gazelle\Manager\User()): array {
        return $this->resolvedLastInterval($userMan, '1 MONTH');
    }

    /**
     * How many open reports exist for this group
     */
    public function totalReportsTGroup(\Gazelle\TGroup $tgroup): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.GroupID = ?
            ", $tgroup->id
        );
    }

    /**
     * How many open reports exist for this uploader
     */
    public function totalReportsUploader(\Gazelle\User $user): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.UserID = ?
            ", $user->id
        );
    }

    /**
     * How many open reports exist for this torrent
     */
    public function totalReportsTorrent(\Gazelle\Torrent|\Gazelle\TorrentDeleted $torrent): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM reportsv2 WHERE Status != 'Resolved' AND TorrentID = ?
            ", $torrent->id
        );
    }

    public function setSearchFilter(array $filter): static {
        $this->filter = $filter;
        return $this;
    }

    protected function searchConfigure(): array {
        if (!isset($this->filter)) {
            return [[], [], []];
        }
        $cond = [];
        $args = [];
        if (isset($this->filter['reporter'])) {
            $cond[] = "r.\"ReporterID\" = {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filter['reporter']->id;
        }
        if (isset($this->filter['handler'])) {
            $cond[] = "r.\"ResolverID\" = {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filter['handler']->id;
        }
        if (isset($this->filter['uploader'])) {
            $cond[] = "coalesce(t.\"UserID\", dt.\"UserID\") = {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filter['uploader']->id;
        }
        if (array_key_exists('report-type', $this->filter)) {
            $cond[] = "r.\"Type\" = any({$this->pg()->placeholder(count($args))})";
            $args[] = $this->pg()->arrayConverter()->output($this->filter['report-type']);
        }
        if (array_key_exists('dt-from', $this->filter)) {
            $cond[] = "r.\"ReportedTime\" >= {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filter['dt-from'];
        }
        if (array_key_exists('dt-until', $this->filter)) {
            $cond[] = "r.\"ReportedTime\"::timestamptz <= {$this->pg()->placeholder(count($args))}::timestamptz + interval '1 day'";
            $args[] = $this->filter['dt-until'];
        }
        if (array_key_exists('torrent', $this->filter)) {
            $cond[] = "r.\"TorrentID\" = {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filter['torrent'];
        }
        if (array_key_exists('group', $this->filter)) {
            $cond[] = "coalesce(t.\"GroupID\", t.\"GroupID\") = {$this->pg()->placeholder(count($args))}";
            $args[] = $this->filter['group'];
        }
        return [
            $cond ? ('where ' .  implode(' and ', $cond)) : '',
            $args,
        ];
    }

    public function searchTotal(): int {
        [$where, $args] = $this->searchConfigure();
        return $this->pg()->scalarex(<<<END_SQL
            select count(*)
            from relay.reportsv2             r
            left join relay.torrents         t on (t."ID" = r."TorrentID")
            left join relay.deleted_torrents dt on (dt."ID" = r."TorrentID")
            left join relay.torrents_group   tg on (tg."ID" = coalesce(t."GroupID", dt."GroupID"))
            $where
            END_SQL, ...$args
        );
    }

    public function searchList(
        int $limit,
        int $offset,
        \Gazelle\Manager\User $userMan = new \Gazelle\Manager\User(),
    ): array {
        [$where, $args] = $this->searchConfigure();
        $limitPH  = $this->pg()->placeholder(count($args));
        $args[]   = $limit;
        $offsetPH = $this->pg()->placeholder(count($args));
        $args[]   = $offset;
        $result   = $this->pg()->allex(<<<END_SQL
            select r."ID"           as report_id,
                r."ReporterID"      as reporter_id,
                r."ResolverID"      as resolver_id,
                r."TorrentID"       as torrent_id,
                coalesce(t."UserID", dt."UserID")
                                    as uploader_id,
                dt."ID" is not null as is_deleted,
                r."Type",
                r."ReportedTime"
            from relay.reportsv2             r
            left join relay.torrents         t on (t."ID" = r."TorrentID")
            left join relay.deleted_torrents dt on (dt."ID" = r."TorrentID")
            left join relay.torrents_group   tg on (tg."ID" = coalesce(t."GroupID", dt."GroupID"))
            $where
            order by r."ReportedTime" desc
            limit $limitPH offset $offsetPH
            END_SQL, ...$args
        );

        $list = [];
        $cache = []; // Avoid looking up a user more than once
        foreach ($result as $r) {
            foreach (['reporter_id', 'resolver_id', 'uploader_id'] as $id) {
                if ($r[$id] && !isset($cache[$r[$id]])) {
                    $cache[$r[$id]] = $userMan->findById($r[$id]);
                }
            }
            $r['reporter'] = $cache[$r['reporter_id']];
            $r['resolver'] = $cache[$r['resolver_id']] ?? null; // unclaimed
            $r['uploader'] = $cache[$r['uploader_id']] ?? null; // sometimes there is no uploader information
            $r['torrent']  = $r['is_deleted']
                ? $this->torMan->findDeletedById($r['torrent_id'])
                : $this->torMan->findById($r['torrent_id']);
            $list[] = $r;
        }
        return $list;
    }
}
