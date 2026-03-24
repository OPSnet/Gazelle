<?php

namespace Gazelle\Better;

use Gazelle\Enum\BetterFilter;

abstract class AbstractBetter extends \Gazelle\Base {
    final public const CACHE_TOTAL = 'better_%s_%s_total';

    protected string $baseQuery;
    protected string $countBy;
    protected string $orderBy;
    protected string $field;
    protected string $search;
    protected string $distinct;
    protected bool $dirty    = true;
    protected array $where = [];
    protected array $args  = [];

    public function __construct(
        protected \Gazelle\User $user,
        protected BetterFilter $filter,
        protected \Gazelle\BaseManager $manager,
    ) {}

    /**
     * This defines the SQL queries needed to perform the
     * SELECT count(*) and a list of SELECT ID FROM ...
     * for the needs of improvement.
     */
    abstract public function configure(): void;

    /**
     * The heading shown on the Better page for this type of improvement
     */
    abstract public function heading(): string;

    /**
     * What kind of thing is being improved?
     *
     * @return string one of ['artist', 'group', 'torrent']
     */
    abstract public function mode(): string;

    public function setSearch(string $search): static {
        $this->dirty = true;
        $this->search = $search;
        return $this;
    }

    public function search(): ?string {
        return $this->search ?? null;
    }

    public function addArtistUserSnatchJoin(): static {
        $this->baseQuery .= <<<END_SQL
            inner join (
                select distinct aa."ArtistID"
                from relay.torrents_artists    ta
                inner join relay.torrents      t  on (t."GroupID" = ta."GroupID")
                inner join relay.artists_alias aa on (ta."AliasID" = aa."AliasID")
                inner join relay.xbt_snatched  x  on (x.fid = t."ID" and x.uid = {$this->pg()->placeholder(count($this->args))})
            ) s on (s."ArtistID" = a."ArtistID")
        END_SQL;
        $this->args[] = $this->user->id;
        return $this;
    }

    public function addArtistUserUploadJoin(): static {
        $this->baseQuery .= <<<END_SQL
            inner join (
                select distinct aa."ArtistID"
                from relay.torrents               t
                inner join relay.torrents_group   tg on (tg."ID" = t."GroupID")
                inner join relay.torrents_artists ta on (ta."GroupID" = tg."ID")
                inner join relay.artists_alias    aa on (ta."AliasID" = aa."AliasID")
                where t."UserID" = {$this->pg()->placeholder(count($this->args))}
            ) s ON (s."ArtistID" = a."ArtistID")
        END_SQL;
        $this->args[] = $this->user->id;
        return $this;
    }

    public function buildQuery(): string {
        $query = $this->baseQuery;
        if ($this->where) {
            $query .= " where " . implode(' and ', $this->where);
        }
        return $query;
    }

    protected function build(): void {
        $this->args = [];
        $this->configure();

        if ($this->mode() === 'artist') {
            if ($this->filter === BetterFilter::snatched) {
                $this->addArtistUserSnatchJoin();
            } elseif ($this->filter === BetterFilter::uploaded) {
                $this->addArtistUserUploadJoin();
            }
        }
        if (isset($this->search)) {
            switch ($this->mode()) {
                case 'artist':
                    $this->where[] = "aa.\"Name\" ~~* ('%' || {$this->pg()->placeholder(count($this->args))} || '%')";
                    $this->args[] = $this->search;
                    break;
                case 'group':
                case 'torrent':
                    $this->where[] = "tg.\"Name\" ~~* ('%' || {$this->pg()->placeholder(count($this->args))} || '%')";
                    $this->args[] = $this->search;
                    break;
            }
        }
    }

    public function totalCacheKey(): string {
        $class = explode('\\', static::class);
        return sprintf(self::CACHE_TOTAL, strtolower(array_pop($class)), $this->filter->name);
    }

    public function totalSql(): string {
        $this->build();
        return "select count("
            . (isset($this->distinct) ? 'distinct ' : '')
            . "{$this->field}) {$this->buildQuery()}";
    }

    public function total(): int {
        $key = $this->totalCacheKey();
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $total =  $this->pg()->scalarex($this->totalSql(), ...$this->args);
            self::$cache->cache_value($key, $total, 1200);
        }
        return $total;
    }

    public function pageSql(int $limit, int $offset): string {
        $this->build();
        $p1 = $this->pg()->placeholder(count($this->args));
        $this->args[] = $limit;
        $p2 = $this->pg()->placeholder(count($this->args));
        $this->args[] = $offset;
        return "select " . (
            isset($this->distinct)
                ? "distinct {$this->field}, {$this->distinct}"
                : $this->field
            )
            . " {$this->buildQuery()} {$this->orderBy} limit $p1 offset $p2";
    }

    public function page(int $limit, int $offset): array {
        return array_map(
            fn ($id) => $this->manager->findById($id),
            $this->pg()->columnex($this->pageSql($limit, $offset), ...$this->args),
        );
    }
}
