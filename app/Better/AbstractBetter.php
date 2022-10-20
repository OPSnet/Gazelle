<?php

namespace Gazelle\Better;

abstract class AbstractBetter extends \Gazelle\Base {

    const CACHE_TOTAL = 'better_%s_total';

    protected string $baseQuery;
    protected string $countBy;
    protected string $orderBy;
    protected string $field;
    protected string $search;
    protected bool $distinct = false;
    protected bool $dirty    = true;
    protected array $where = [];
    protected array $args  = [];

    public function __construct(
        protected \Gazelle\User $user,
        protected string $filter,
        protected \Gazelle\Base $manager,
    ) { }

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

    public function setSearch(string $search): AbstractBetter {
        $this->dirty = true;
        $this->search = $search;
        return $this;
    }

    public function search(): ?string {
        return isset($this->search) ? $this->search : null;
    }

    public function addArtistUserSnatchJoin(): AbstractBetter {
        $this->baseQuery .= " INNER JOIN (
            SELECT DISTINCT ta.ArtistID
            FROM torrents_artists ta
            INNER JOIN torrents t ON (t.GroupID = ta.GroupID)
            INNER JOIN xbt_snatched x ON (x.fid = t.ID AND x.uid = ?)
        ) s ON (s.ArtistID = a.ArtistID)";
        $this->args[] = $this->user->id();
        return $this;
    }

    public function addArtistUserUploadJoin(): AbstractBetter {
        $this->baseQuery .= "INNER JOIN (
            SELECT DISTINCT ta.ArtistID
            FROM torrents t
            INNER JOIN torrents_group tg ON (tg.ID = t.GroupID)
            INNER JOIN torrents_artists ta ON (ta.GroupID = tg.ID)
            WHERE t.UserID = ?
            ) s ON (s.ArtistID = a.ArtistID)";
        $this->args[] = $this->user->id();
        return $this;
    }

    public function buildQuery(): string {
        $query = $this->baseQuery;
        if ($this->where) {
            $query .= " WHERE " . implode(' AND ', $this->where);
        }
        return $query;
    }

    protected function build(): void {
        if ($this->dirty) {
            $this->dirty = false;
            $this->configure();

            if ($this->mode() === 'artist') {
                if ($this->filter === 'snatched') {
                    $this->addArtistUserSnatchJoin();
                } elseif ($this->filter === 'uploaded') {
                    $this->addArtistUserUploadJoin();
                }
            }
            if (isset($this->search)) {
                switch ($this->mode()) {
                    case 'artist':
                        $this->where[] = 'a.Name LIKE ?';
                        $this->args[] = "%{$this->search}%";
                        break;
                    case 'group':
                    case 'torrent':
                        $this->where[] = "tg.Name LIKE ?";
                        $this->args[] = "%{$this->search}%";
                        break;
                }
            }
        }
    }

    public function listSql(): string {
        $this->build();
        return "SELECT " . ($this->distinct ? 'DISTINCT ' : '') . $this->field
            . " " . $this->buildQuery()
            . " " . $this->orderBy
            . " LIMIT ? OFFSET ?"
        ;
    }

    public function list(int $limit, int $offset): array {
        self::$db->prepared_query($this->listSql(), ...[...$this->args, $limit, $offset]);
        return array_map(fn ($id) => $this->manager->findById($id), self::$db->collect(0, false)); /** @phpstan-ignore-line */
    }

    protected function totalCacheKey(): string {
        $class = explode('\\', get_class($this));
        return sprintf(self::CACHE_TOTAL, strtolower(array_pop($class)));
    }

    public function totalSql(): string {
        $this->build();
        return "SELECT count(" . ($this->distinct ? "DISTINCT {$this->field}" : '*') . ") " . $this->buildQuery();
    }

    public function total(): int {
        $key = $this->totalCacheKey();
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $total =  self::$db->scalar($this->totalSql(), ...$this->args);
            self::$cache->cache_value($key, $total, 1200);
        }
        return $total;
    }
}
