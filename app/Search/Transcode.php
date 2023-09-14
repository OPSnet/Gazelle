<?php

namespace Gazelle\Search;

class Transcode extends \Gazelle\Base {
    /**
     * A class to deal with finding Lossless uploads that can be transcoded,
     * based on what a user is seeding or has snatched.
     */

    protected const CACHE_KEY     = 'u_better_%s_%d';
    protected const MODE_ANY      = 0;
    protected const MODE_SEEDING  = 1;
    protected const MODE_SNATCHED = 2;
    protected const MODE_UPLOADED = 3;
    protected int $mode;

    protected bool $want320;
    protected bool $wantV0;
    protected string $search;

    public function __construct(
        protected \Gazelle\User $user,
        protected \Gazelle\Manager\Torrent $torMan
    ) {}

    public function setModeAny(): static {
        $this->mode = self::MODE_ANY;
        return $this;
    }

    public function setModeSeeding(): static {
        $this->mode = self::MODE_SEEDING;
        return $this;
    }

    public function setModeSnatched(): static {
        $this->mode = self::MODE_SNATCHED;
        return $this;
    }

    public function setModeUploaded(): static {
        $this->mode = self::MODE_UPLOADED;
        return $this;
    }

    public function setSearch(string $search): static {
        $this->search = $search;
        return $this;
    }

    public function hasv0(): bool {
        return isset($this->wantV0);
    }

    public function wantV0(): static {
        $this->wantV0 = true;
        return $this;
    }

    public function has320(): bool {
        return isset($this->want320);
    }

    public function want320(): static {
        $this->want320 = true;
        return $this;
    }

    public function queryInit(): array {
        $cond = ["tg.CategoryID = 1 AND t.Format = 'FLAC'"];
        $args = [];

        // need to join additional tables to restrict the search?
        $distinct = '';
        switch ($this->mode ?? self::MODE_ANY) {
            case self::MODE_SEEDING:
                $distinct = 'DISTINCT'; // may be seeding from more than one location
                $join = 'INNER JOIN xbt_files_users xfu ON (xfu.fid = t.ID)';
                $cond[] = 'active = 1 AND remaining = 0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = ?';
                $args[] = $this->user->id();
                break;
            case self::MODE_SNATCHED:
                $distinct = 'DISTINCT'; // may have snatched more than once
                $join = 'INNER JOIN xbt_snatched xs ON (xs.fid = t.ID)';
                $cond[] = 'xs.uid = ?';
                $args[] = $this->user->id();
                break;
            case self::MODE_UPLOADED:
                $join = '/* uploaded */';
                $cond[] = 't.UserID = ?';
                $args[] = $this->user->id();
                break;
            default:
                $join = '/* any */';
                break;
        }

        if (isset($this->search)) {
            $cond[] = "tg.Name LIKE concat('%', ?, '%')";
            $args[] = $this->search;
        }

        // what opportunities are there?
        if (isset($this->wantV0) || isset($this->want320)) {
            $want = [];
            if (isset($this->want320)) {
                $want[] = 'b.want_320 = 1';
            }
            if (isset($this->wantV0)) {
                $want[] = 'b.want_v0 = 1';
            }
            $cond[] = '(' . implode(' OR ', $want) . ')';
        }

        $sql = "FROM torrents t
            INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
            INNER JOIN better_transcode_music b ON (
                b.tgroup_id = t.GroupID
                AND b.edition = concat_ws(
                    char(31),
                    t.Remastered, t.RemasterYear, t.RemasterTitle, t.RemasterRecordLabel, t.RemasterCatalogueNumber
                )
            )
            $join";
        return [$sql, $distinct, $cond, $args];
    }

    public function queryList(int $limit, int $offset): array {
        [$sqlBody, $distinct, $cond, $args] = $this->queryInit();
        $sql = "SELECT $distinct t.ID AS source, b.want_320, b.want_v0
            $sqlBody
            WHERE " . implode(' AND ', $cond) . " LIMIT ? OFFSET ?";
        return [$sql, array_merge($args, [$limit, $offset])];
    }

    public function list(int $limit, int $offset): array {
        $sql = null;
        $args = null;
        $key = sprintf(self::CACHE_KEY,
            match ($this->mode ?? self::MODE_ANY) {
                self::MODE_SEEDING  => 'seed',
                self::MODE_SNATCHED => 'snatch',
                self::MODE_UPLOADED => 'up',
                default             => 'any',
            }
            . (isset($this->want320) ? '_320' : '')
            . (isset($this->wantV0)  ? '_v0'  : ''),
            $this->user->id()
        );
        $list = self::$cache->get_value($key);
        $list = false;
        [$sql, $args] = $this->queryList($limit, $offset);
        self::$db->prepared_query($sql, ...$args);
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        if (!isset($this->search)) {
            self::$cache->cache_value($key, $list, 3600);
        }
        foreach ($list as &$row) {
            $row['torrent'] = $this->torMan->findById($row['source']);
        }
        return $list;
    }

    public function queryTotal(): array {
        [$sqlBody, $distinct, $cond, $args] = $this->queryInit();
        $sql = "SELECT count($distinct t.ID) AS `all`,
                sum(b.want_320) AS total_320,
                sum(b.want_v0) AS total_v0
            $sqlBody
            WHERE " . implode(' AND ', $cond);
        return [$sql, $args];
    }

    public function total(): array {
        $sql = null;
        $args = null;
        $key = sprintf(self::CACHE_KEY,
            match ($this->mode ?? self::MODE_ANY) {
                self::MODE_SEEDING  => 'total_seed',
                self::MODE_SNATCHED => 'total_snatch',
                self::MODE_UPLOADED => 'total_up',
                default             => 'total_any',
            }
            . (isset($this->want320) ? '_320' : '')
            . (isset($this->wantV0)  ? '_v0'  : ''),
            $this->user->id()
        );
        $total = self::$cache->get_value($key);
        $total = false;
        [$sql, $args] = $this->queryTotal();
        $total = self::$db->rowAssoc($sql, ...$args);
        if (!isset($this->search)) {
            self::$cache->cache_value($key, $total, 3600);
        }
        return $total;
    }
}
