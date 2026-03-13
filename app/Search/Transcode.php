<?php

namespace Gazelle\Search;

use Gazelle\Enum\BetterEncoding;
use Gazelle\Enum\BetterFilter;


class Transcode extends \Gazelle\Base {
    /**
     * A class to deal with finding Lossless uploads that can be transcoded,
     * based on what a user is seeding or has snatched.
     */

    /*
    with u as (
        select distinct t."ID" as id_torrent,
            t."GroupID" as id_tgroup,
             concat_ws(
                 chr(31),
                 t."RemasterYear", t."RemasterTitle", t."RemasterRecordLabel", t."RemasterCatalogueNumber"
             ) as edition
        from relay.xbt_files_users xfu
        inner join relay.torrents t on (xfu.fid = t."ID")
        where t."Format" = 'FLAC'
            and xfu.active = 1
            and xfu.remaining = 0
            and xfu.mtime > extract(epoch from (now() - interval '1 hour'))::integer
            and xfu.uid = $1
    )
    select u.id_torrent AS source, b.want_320, b.want_v0
    from u
    inner join better_tgroup b using (id_tgroup, edition)
    where (b.want_320 is true and b.want_v0 is true)
    limit 10 offset 0;
    */

    protected const CACHE_KEY          = 'u_better_2_%s_%s_%d';
    protected BetterFilter $mode       = BetterFilter::any;
    protected BetterEncoding $encoding = BetterEncoding::any;
    protected string $search;

    public function __construct(
        protected \Gazelle\User $user,
        protected \Gazelle\Manager\Torrent $torMan = new \Gazelle\Manager\Torrent(),
    ) {}

    public function setEncoding(BetterEncoding $encoding): static {
        $this->encoding = $encoding;
        return $this;
    }

    public function setMode(BetterFilter $mode): static {
        $this->mode = $mode;
        return $this;
    }

    public function setSearch(string $search): static {
        $this->search = $search;
        return $this;
    }

    public function placeholder(int $n): string {
        return '$' . (1 + $n);
    }

    public function queryInit(): array {
        // need to join additional tables to restrict the search?
        $join = isset($this->search)
            ? 'inner join relay.torrents_group tg ON (tg."ID" = t."GroupID")'
            : '/* no join */';
        $cond = [];
        $args = [];
        switch ($this->mode) {
            case BetterFilter::seeding:
                $distinct = ' distinct'; // may be seeding more than once
                $body = <<<END_SQL
                    from relay.torrents t
                    $join
                    inner join relay.xbt_files_users xfu on (xfu.fid = t."ID")
                END_SQL;
                $cond[] = <<<END_SQL
                    t."Format" = 'FLAC'
                        and xfu.active = 1
                        and xfu.remaining = 0
                        and xfu.mtime > extract(epoch from (now() - interval '1 hour'))::integer
                        and xfu.uid = {$this->placeholder(count($args))}
                END_SQL;
                $args[] = $this->user->id;
                break;
            case BetterFilter::snatched:
                $distinct = ' distinct'; // may have snatched more than once
                $body = <<<END_SQL
                    from relay.torrents t
                    $join
                    inner join relay.xbt_snatched xs on (xs.fid = t."ID")
                END_SQL;
                $cond[] = "xs.uid = {$this->placeholder(count($args))}";
                $args[] = $this->user->id;
                break;
            case BetterFilter::uploaded:
                $distinct = '';
                $body     = "from relay.torrents t $join";
                $cond[]   = "t.\"UserID\" = {$this->placeholder(count($args))}";
                $args[]   = $this->user->id;
                break;
            default:
                $distinct = '';
                $body     = "from relay.torrents t $join";
                break;
        }

        if (isset($this->search)) {
            $cond[] = "tg.\"Name\" ~~* ('%' || {$this->placeholder(count($args))} || '%')";
            $args[] = $this->search;
        }

        $where = $cond === []
            ? ''
            : "where " . implode(' and ', $cond);

        return [
            <<<END_SQL
            with u as (
                select $distinct t."ID" as id_torrent,
                    t."GroupID" as id_tgroup,
                     concat_ws(
                         chr(31),
                         t."RemasterYear", t."RemasterTitle", t."RemasterRecordLabel", t."RemasterCatalogueNumber"
                     ) as edition
                $body
                $where
            )
            END_SQL,
            match ($this->encoding) {
                BetterEncoding::all    => '(b.want_320 is true and b.want_v0 is true)',
                BetterEncoding::any    => '(b.want_320 is true or b.want_v0 is true)',
                BetterEncoding::cbr320 => 'b.want_320 is true',
                BetterEncoding::v0     => 'b.want_v0 is true',
            },
            $args,
        ];
    }

    public function queryList(int $limit, int $offset): array {
        [$cte, $cond, $args] = $this->queryInit();
        return [
            <<<END_SQL
                $cte
                select u.id_torrent as source, b.want_320, b.want_v0
                from u
                inner join better_tgroup b using (id_tgroup, edition)
                where $cond
                limit {$this->placeholder(count($args))} offset {$this->placeholder(count($args) + 1)}
            END_SQL,
            [
                ...$args,
                $limit,
                $offset
            ],
        ];
    }

    public function list(int $limit, int $offset): array {
        $key = null;
        $list = false;
        if (!isset($this->search)) {
            $key = sprintf(self::CACHE_KEY,
                $this->mode->name,
                $this->encoding->value,
                $this->user->id
            );
            $list = self::$cache->get_value($key);
        }
        if ($list === false) {
            [$sql, $args] = $this->queryList($limit, $offset);
            $list = $this->pg()->allex($sql, ...$args);
            if ($key) {
                self::$cache->cache_value($key, $list, 3600);
            }
        }

        foreach ($list as &$row) {
            $row['torrent'] = $this->torMan->findById($row['source']);
        }
        return $list;
    }

    public function queryTotal(): array {
        [$cte, $cond, $args] = $this->queryInit();
        return [
            <<<END_SQL
                $cte
                select count(u.id_torrent) as "all",
                    sum(b.want_320::integer) as total_320,
                    sum(b.want_v0::integer) as total_v0
                from u
                inner join better_tgroup b using (id_tgroup, edition)
                where $cond
            END_SQL,
            $args
        ];
    }

    public function total(): array {
        $key = null;
        $total = false;
        if (!isset($this->search)) {
            $key = sprintf(self::CACHE_KEY,
                'total_' . $this->mode->name,
                $this->encoding->value,
                $this->user->id
            );
            $total = self::$cache->get_value($key);
        }
        if ($total === false) {
            [$sql, $args] = $this->queryTotal();
            $total = $this->pg()->rowAssocex($sql, ...$args);
            if ($key) {
                self::$cache->cache_value($key, $total, 3600);
            }
        }
        return $total;
    }
}
