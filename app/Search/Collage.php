<?php

namespace Gazelle\Search;

use Gazelle\User;
use Gazelle\Util\SortableTableHeader;

class Collage extends \Gazelle\Base {
    /* In queries, the collages table is aliased to c */

    protected bool $bookmarkView = false;
    protected bool $configured   = false;
    protected bool $contributor;
    protected bool $tagAll       = true;
    protected bool $filtered     = true;

    protected string $lookup   = 'Name';
    protected string $userLink = '';

    protected array $category   = [];
    protected array $taglist    = [];
    protected array $joinList   = [];
    protected array $whereList  = ["c.\"Deleted\" = '0'"];
    protected array $filter     = [];
    protected array $args       = [];

    /* the collapsed version of the above */
    protected string $join;
    protected string $where;

    protected SortableTableHeader $header;

    public function header(): SortableTableHeader {
        return $this->header;
    }

    public function isFilteredView(): bool {
        return count($this->whereList) > 1;
    }

    public function isBookmarkView(): bool {
        return $this->bookmarkView;
    }

    public function isContributor(): bool {
        return isset($this->contributor);
    }

    public function isSelectedCategory(int $id): bool {
        return $this->category === [] || in_array($id, $this->category);
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

    public function hiddenTags(array $list = HIDDEN_TAGS): array {
        return $this->pg()->columnex('
            select name
            from tag
            where id_tag = any($1)
            ', $this->pg()->integerConverter()->output($list)
        );
    }

    public function setBookmarkView(User $user): static {
        $this->bookmarkView = true;
        $this->userLink     = $user->link();
        $this->joinList[]   = 'inner join bookmark_collage bc on (c."ID" = bc.id_collage)';
        $this->whereList[]  = "bc.id_user = {$this->pg()->placeholder(count($this->args))}";
        $this->args[]       = $user->id;
        return $this;
    }

    public function setCategory(array $category): static {
        $this->category = array_filter(
            $category,
            fn ($id) => in_array($id, array_keys(COLLAGE))
        );
        return $this;
    }

    public function setContributor(User $user): static {
        $this->contributor = true;
        $this->userLink    = $user->link();
        $placeholder       = $this->pg()->placeholder(count($this->args));
        $this->whereList[] = <<<END_SQL
            c."ID" in (select distinct ct."CollageID" from relay.collages_torrents ct where ct."UserID" = $placeholder)
        END_SQL;
        $this->args[] = $user->id;
        return $this;
    }

    public function setLookup(string $lookup): static {
        $lookup = ucfirst($lookup);
        if (in_array($lookup, ['Name', 'Description'])) {
            $this->lookup = $lookup;
        }
        return $this;
    }

    public function setPersonal(): static {
        $this->whereList[] = 'c."CategoryID" = 0';
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

    public function setUser(User $user): static {
        $this->userLink = $user->link();
        $this->whereList[] = <<<END_SQL
            c."UserID" = {$this->pg()->placeholder(count($this->args))}
        END_SQL;
        $this->args[]  = $user->id;
        return $this;
    }

    public function setWordlist(string $wordlist): static {
        if (preg_match_all('/(\S+)/', $wordlist, $match)) {
            foreach ($match[0] as $m) {
                $this->whereList[] = <<<END_SQL
                    c."{$this->lookup}" ~~* ('%' || {$this->pg()->placeholder(count($this->args))} || '%')
                END_SQL;
                $this->args[] = $m;
            }
        }
        return $this;
    }

    public function disableFilter(): static {
        $this->filtered = false;
        return $this;
    }

    public function configure(): void {
        $this->header = new \Gazelle\Util\SortableTableHeader('time', [
            'time'        => ['dbColumn' => 'c."ID"',          'defaultSort' => 'desc', 'text' => 'Created'],
            'name'        => ['dbColumn' => 'c."Name"',        'defaultSort' => 'asc',  'text' => 'Collage'],
            'subscribers' => ['dbColumn' => 'c."Subscribers"', 'defaultSort' => 'desc', 'text' => 'Subscribers'],
            'torrents'    => ['dbColumn' => 'c."NumTorrents"', 'defaultSort' => 'desc', 'text' => 'Entries'],
            'updated'     => ['dbColumn' => 'c.updated',       'defaultSort' => 'desc', 'text' => 'Updated'],
        ]);
        if ($this->category) {
            sort($this->category);
            if (implode(' ', $this->category) !== implode(' ', array_keys(COLLAGE))) {
                $this->whereList[] = "c.\"CategoryID\" = any({$this->pg()->placeholder(count($this->args))})";
                $this->args[] = $this->pg()->integerConverter()->output($this->category);
            }
        }
        if ($this->taglist) {
            $cond = [];
            foreach ($this->taglist as $tag) {
                $cond[] = "c.\"TagList\" ~~* ('%' || {$this->pg()->placeholder(count($this->args))} || '%')";
                $this->args[] = $tag;
            }
            $this->whereList[] = '(' . implode($this->tagAll ? ' AND ' : ' OR ', $cond) . ')';
        }
        if ($this->filtered) {
            foreach ($this->hiddenTags() as $htag) {
                $this->whereList[] = "c.\"TagList\" !~~* ('%' || {$this->pg()->placeholder(count($this->args))} || '%')";
                $this->args[] = $htag;
            }
        }
        $this->join = implode(' ', $this->joinList);
        $this->where = implode(' and ', $this->whereList);
        $this->configured = true;
    }

    public function total(): int {
        if (!$this->configured) {
            $this->configure();
        }
        return $this->pg()->scalarex("
            select count(*)
            from relay.collages c {$this->join}
            where {$this->where}
            ", ...$this->args
        );
    }

    public function page(int $limit, int $offset): array {
        if (!$this->configured) {
            $this->configure();
        }
        $orderBy  = $this->header->orderBy();
        $orderDir = $this->header->dir();
        $args     = $this->args;
        $limitPh  = "limit {$this->pg()->placeholder(count($args))}";
        $args[]   = $limit;
        $offsetPh = "offset {$this->pg()->placeholder(count($args))}";
        $args[]   = $offset;
        return $this->pg()->allex(<<<END_SQL
            SELECT c."ID"                         as id,
                c."Name"                          as name,
                c."NumTorrents"                   as total,
                c."TagList"                       as taglist,
                string_to_array(c."TagList", ' ') as tag,
                c."CategoryID"                    as category_id,
                c."UserID"                        as user_id,
                c."Subscribers"                   as subscriber_total,
                c.updated
            from relay.collages c {$this->join}
            where {$this->where}
            order by $orderBy $orderDir, id
            $limitPh $offsetPh
            END_SQL,
                ...$args
        );
    }
}
