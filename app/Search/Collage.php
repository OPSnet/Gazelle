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
    protected array $joinList = [];
    protected array $whereList = ["c.Deleted = '0'"];
    protected array $args     = [];

    /* the collapsed version of the above */
    protected string $join;
    protected string $where;

    protected \Gazelle\Util\SortableTableHeader $header;

    public function header(): \Gazelle\Util\SortableTableHeader {
        return $this->header;
    }

    public function isFilteredView(): bool {
        return count($this->whereList) > 1;
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

    public function setBookmarkView(\Gazelle\User $user): static {
        $this->bookmarkView = true;
        $this->userLink = $user->link();
        $this->joinList[]  = "INNER JOIN bookmarks_collages AS bc ON (c.ID = bc.CollageID)";
        $this->whereList[] = "bc.UserID = ?";
        $this->args[]  = $user->id();
        return $this;
    }

    public function setCategory(array $category): static {
        $this->category = array_filter($category, fn ($id) => in_array($id, array_keys(COLLAGE)));
        return $this;
    }

    public function setContributor(\Gazelle\User $user): static {
        $this->contributor = true;
        $this->userLink = $user->link();
        $this->whereList[] = "c.ID IN (SELECT DISTINCT CollageID FROM collages_torrents WHERE UserID = ?)";
        $this->args[] = $user->id();
        return $this;
    }

    public function setLookup(string $lookup): static {
        if (!in_array($lookup, ['name', 'description'])) {
            $lookup = 'name';
        }
        $this->lookup = $lookup;
        return $this;
    }

    public function setPersonal(): static {
        $this->whereList[] = 'c.CategoryID = 0';
        return $this;
    }

    public function setTagAll(bool $tagAll): static {
        $this->tagAll = $tagAll;
        return $this;
    }

    public function setTaglist(array $taglist): static {
        $this->taglist = $taglist;
        return $this;
    }

    public function setUser(\Gazelle\User $user): static {
        $this->userLink = $user->link();
        $this->whereList[] = 'c.UserID = ?';
        $this->args[]  = $user->id();
        return $this;
    }

    public function setWordlist(string $wordlist): static {
        if (preg_match_all('/(\S+)/', $wordlist, $match)) {
            array_push($this->whereList, ...array_fill(0, count($match[0]), "c." . $this->lookup . " LIKE concat('%', ?, '%')"));
            array_push($this->args, ...$match[0]);
        }
        return $this;
    }

    public function configure(): void {
        $this->header = new \Gazelle\Util\SortableTableHeader('time', [
            'time'        => ['dbColumn' => 'c.ID',          'defaultSort' => 'desc', 'text' => 'Created'],
            'name'        => ['dbColumn' => 'c.Name',        'defaultSort' => 'asc',  'text' => 'Collage'],
            'subscribers' => ['dbColumn' => 'c.Subscribers', 'defaultSort' => 'desc', 'text' => 'Subscribers'],
            'torrents'    => ['dbColumn' => 'c.NumTorrents', 'defaultSort' => 'desc', 'text' => 'Entries'],
            'updated'     => ['dbColumn' => 'c.Updated',     'defaultSort' => 'desc', 'text' => 'Updated'],
        ]);
        if ($this->category) {
            sort($this->category);
            if (implode(' ', $this->category) !== implode(' ', array_keys(COLLAGE))) {
                $this->whereList[] = "c.CategoryID IN (" . placeholders($this->category) . ')';
                array_push($this->args, ...$this->category);
            }
        }
        if ($this->taglist) {
            $this->whereList[] = '(' . implode($this->tagAll ? ' AND ' : ' OR ',
                    array_fill(0, count($this->taglist), "c.TagList LIKE concat('%', ?, '%')"))
                . ')';
            array_push($this->args, ...$this->taglist);
        }
        $this->join = implode(' ', $this->joinList);
        $this->where = implode(' AND ', $this->whereList);
        $this->configured = true;
    }

    public function total(): int {
        if (!$this->configured) {
            $this->configure();
        }
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM collages AS c {$this->join}
            WHERE {$this->where}
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
            SELECT c.ID        AS id,
                c.Name         AS name,
                c.NumTorrents  AS total,
                c.TagList      AS tag_list,
                c.CategoryID   AS category_id,
                c.UserID       AS user_id,
                c.Subscribers  AS subscriber_total,
                c.Updated      AS updated
            FROM collages AS c {$this->join}
            WHERE {$this->where}
            ORDER BY $orderBy $orderDir
            LIMIT ? OFFSET ?
            ", ...[...$this->args, $limit, $offset]
        );
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$c) {
            $c['tag'] = explode(' ', $c['tag_list']);
        }
        return $list;
    }
}
