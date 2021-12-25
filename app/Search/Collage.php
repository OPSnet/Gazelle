<?php

namespace Gazelle\Search;

class Collage extends \Gazelle\Base {

    /* In queries, the collages table is aliased to c */

    protected bool $bookmarkView = false;
    protected bool $configured = false;
    protected bool $contributor = false;
    protected bool $tagAll = true;

    protected string $lookup = 'name';
    protected string $userLink = '';

    protected array $category = [];
    protected array $taglist  = [];
    protected array $join     = [];
    protected array $where    = ["c.Deleted = '0'"];
    protected array $args     = [];

    /* the collapsed version of the above */
    protected string $_join;
    protected string $_where;

    protected \Gazelle\Util\SortableTableHeader $header;

    public function header(): \Gazelle\Util\SortableTableHeader {
        return $this->header;
    }

    public function isBookmarkView(): bool {
        return $this->bookmarkView;
    }

    public function isContributor(): bool {
        return $this->contributor;
    }

    public function isSelectedCategory(int $id): bool {
        return empty($this->category) || in_array($id, $this->category);
    }

    public function isTagAll(): bool {
        return $this->tagAll;
    }

    public function lookup(): string {
        return $this->lookup;
    }

    public function userLink(): string {
        return $this->userLink;
    }

    public function setBookmarkView(\Gazelle\User $user) {
        $this->bookmarkView = true;
        $this->userLink = $user->link();
        $this->join[]  = "INNER JOIN bookmarks_collages AS bc ON (c.ID = bc.CollageID)";
        $this->where[] = "bc.UserID = ?";
        $this->args[]  = $user->id();
        return $this;
    }

    public function setCategory(array $category) {
        $this->category = array_filter($category, fn ($id) => in_array($id, array_keys(COLLAGE)));
        return $this;
    }

    public function setContributor(\Gazelle\User $user) {
        $this->contributor = true;
        $this->userLink = $user->link();
        $this->where[] = "c.ID IN (SELECT DISTINCT CollageID FROM collages_torrents WHERE UserID = ?)";
        $this->args[] = $user->id();
        return $this;
    }

    public function setLookup(string $lookup) {
        if (!in_array($lookup, ['name', 'description'])) {
            $lookup = 'name';
        }
        $this->lookup = $lookup;
        return $this;
    }

    public function setPersonal() {
        $this->where[] = 'c.CategoryID = 0';
        return $this;
    }

    public function setTagAll(bool $tagAll) {
        $this->tagAll = $tagAll;
        return $this;
    }

    public function setTaglist(array $taglist) {
        $this->taglist = $taglist;
        return $this;
    }

    public function setUser(\Gazelle\User $user) {
        $this->userLink = $user->link();
        $this->where[] = 'c.UserID = ?';
        $this->args[]  = $user->id();
        return $this;
    }

    public function setWordlist(string $wordlist) {
        if (preg_match_all('/(\S+)/', $wordlist, $match)) {
            array_push($this->where, ...array_fill(0, count($match[0]), "c." . $this->lookup . " LIKE concat('%', ?, '%')"));
            array_push($this->args, ...$match[0]);
        }
        return $this;
    }

    public function configure() {
        $this->header = new \Gazelle\Util\SortableTableHeader('time', [
            'time'        => ['dbColumn' => 'ID',            'defaultSort' => 'desc', 'text' => 'Created'],
            'name'        => ['dbColumn' => 'c.Name',        'defaultSort' => 'asc',  'text' => 'Collage'],
            'subscribers' => ['dbColumn' => 'c.Subscribers', 'defaultSort' => 'desc', 'text' => 'Subscribers'],
            'torrents'    => ['dbColumn' => 'c.NumTorrents', 'defaultSort' => 'desc', 'text' => 'Entries'],
            'updated'     => ['dbColumn' => 'c.Updated',     'defaultSort' => 'desc', 'text' => 'Updated'],
        ]);
        if ($this->category) {
            sort($this->category);
            if (implode(' ', $this->category) !== implode(' ', array_keys(COLLAGE))) {
                $this->where[] = "c.CategoryID IN (" . placeholders($this->category) . ')';
                array_push($this->args, ...$this->category);
            }
        }
        if ($this->taglist) {
            $this->where[] = '(' . implode($this->tagAll ? ' AND ' : ' OR ',
                    array_fill(0, count($this->taglist), "c.TagList LIKE concat('%', ?, '%')"))
                . ')';
            array_push($this->args, ...$this->taglist);
        }
        $this->_join = implode(' ', $this->join);
        $this->_where = implode(' AND ', $this->where);
        $this->configured = true;
    }

    public function total(): int {
        if (!$this->configured) {
            $this->configure();
        }
        return self::$db->scalar("
            SELECT count(*)
            FROM collages AS c {$this->_join}
            WHERE {$this->_where}
            ", ...$this->args
        );
    }

    public function page(int $limit, int $offset): array {
        if (!$this->configured) {
            $this->configure();
        }
        $orderBy = $this->header->getOrderBy();
        $orderDir = $this->header->getOrderDir();
        self::$db->prepared_query("
            SELECT c.ID,
                c.Name,
                c.NumTorrents,
                c.TagList,
                c.CategoryID,
                c.UserID,
                c.Subscribers,
                c.Updated
            FROM collages AS c {$this->_join}
            WHERE {$this->_where}
            ORDER BY $orderBy $orderDir
            LIMIT ? OFFSET ?
            ", ...[...$this->args, $limit, $offset]
        );
        return self::$db->to_array(false, MYSQLI_NUM, false);
    }
}
