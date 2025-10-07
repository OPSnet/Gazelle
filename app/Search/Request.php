<?php

namespace Gazelle\Search;

use Gazelle\Enum\SearchTag;
use Gazelle\ReleaseType;
use Gazelle\Tag;
use Gazelle\User;
use Gazelle\Util\SortableTableHeader;

class Request extends \Gazelle\Base {
    protected array               $info;
    protected string              $cond;
    protected array               $args;
    protected SortableTableHeader $heading;

    public function __construct(
        protected \Gazelle\Manager\Request $manager = new \Gazelle\Manager\Request(),
        protected \Gazelle\Manager\Tag     $tagMan  = new \Gazelle\Manager\Tag(),
    ) {}

    public function info(): array {
        return $this->info;
    }

    public function flush(): static {
        unset($this->info, $this->cond, $this->args);
        return $this;
    }

    public function heading(): SortableTableHeader {
        return $this->heading ??= new SortableTableHeader(
            'created',
            [
                'year'     => ['dbColumn' => 'r.year',           'defaultSort' => 'desc', 'text' => 'Year'],
                'votes'    => ['dbColumn' => 'rvs.user_total',   'defaultSort' => 'desc', 'text' => 'Votes'],
                'bounty'   => ['dbColumn' => 'rvs.bounty_total', 'defaultSort' => 'desc', 'text' => 'Bounty'],
                'filled'   => ['dbColumn' => 'r.filled',         'defaultSort' => 'desc', 'text' => 'Filled'],
                'created'  => ['dbColumn' => 'r.created',        'defaultSort' => 'desc', 'text' => 'Created'],
                'lastvote' => ['dbColumn' => 'rvs.last_vote',    'defaultSort' => 'desc', 'text' => 'Last Vote'],
            ]
        );
    }

    public function isBookmarkView(): bool {
        return isset($this->info['bookmarker']);
    }

    public function isShowFilled(): bool {
        return isset($this->info['show_filled']);
    }

    public function setBookmarker(User $user): static {
        $this->info['bookmarker'] = $user;
        return $this;
    }

    public function setCreator(User $user): static {
        $this->info['creator'] = $user;
        return $this;
    }

    public function setFiller(User $user): static {
        $this->info['filler'] = $user;
        return $this;
    }

    public function setVoter(User $user): static {
        $this->info['voter'] = $user;
        return $this;
    }

    /**
     * @param array<int> $categoryList
     */
    public function setCategory(array $categoryList): static {
        if (in_array(count($categoryList), [0, count(CATEGORY)])) {
            return $this;
        }
        $term = [];
        foreach ($categoryList as $idx) {
            if (isset(CATEGORY[$idx])) {
                $term[] = $idx + 1;
            }
        }
        $this->info['category'] = $term;
        return $this;
    }

    public function setEncoding(array $list, bool $strict): static {
        if (!in_array(count($list), [0, count(ENCODING)])) {
            $this->info['encoding'] = [
                'list'   => $list,
                'strict' => $strict,
            ];
        }
        return $this;
    }

    public function setFormat(array $list, bool $strict): static {
        if (!in_array(count($list), [0, count(FORMAT)])) {
            $this->info['format'] = [
                'list'   => $list,
                'strict' => $strict,
            ];
        }
        return $this;
    }

    public function setMedia(array $list, bool $strict): static {
        if (!in_array(count($list), [0, count(MEDIA)])) {
            $this->info['media'] = [
                'list'   => $list,
                'strict' => $strict,
            ];
        }
        return $this;
    }

    public function setReleaseType(
        array $releaseTypeList,
        ReleaseType $rt = new ReleaseType(),
    ): static {
        $all = $rt->list();
        if (in_array(count($releaseTypeList), [0, count($all)])) {
            return $this;
        }
        $term = [];
        foreach ($releaseTypeList as $idx) {
            if (isset($all[$idx])) {
                $term[] = $idx;
            }
        }
        if ($term) {
            $this->info['release_type'] = $term;
        }
        return $this;
    }

    public function setTag(
        string $tagList,
        SearchTag $tagMode,
    ): static {
        if ($tagList === '') {
            return $this;
        }
        $split = preg_split('/\s*,\s*/', $tagList);
        if ($split === false) {
            return $this;
        }
        $include = [];
        $exclude = [];
        foreach ($split as $name) {
            if (preg_match('/^!(.*)$/', $name, $match)) {
                $tag = $this->tagMan->findByName($match[1]);
                if ($tag instanceof Tag) {
                    $exclude[] = $tag;
                }
            } else {
                $tag = $this->tagMan->findByName($name);
                if ($tag instanceof Tag) {
                    $include[] = $tag;
                }
            }
        }
        $this->info['tag'] = [
            'include' => $include,
            'exclude' => $exclude,
            'mode'    => $tagMode,
        ];
        return $this;
    }

    public function setSearch(string $search): static {
        if ($search !== '') {
            $this->info['search'] = $search;
        }
        return $this;
    }

    public function setYear(int $year): static {
        if ($year !== 0) {
            $this->info['year'] = $year;
        }
        return $this;
    }

    public function showFilled(): static {
        $this->info['show_filled'] = true;
        return $this;
    }

    public function filter(): string {
        if (isset($this->cond)) {
            return $this->cond;
        }
        $join = [];
        $cond = [];
        $args = [];
        if (isset($this->info['bookmarker'])) {
            $join[] = 'inner join relay.bookmarks_requests rb on (rb."RequestID" = r.id_request)';
            $cond[] = 'rb."UserID" = ?';
            $args[] = $this->info['bookmarker']->id;
        }
        if (isset($this->info['creator'])) {
            $cond[] = 'r.id_user = ?';
            $args[] = $this->info['creator']->id;
        }
        if (isset($this->info['filler'])) {
            $cond[] = 'r.id_filler = ?';
            $args[] = $this->info['filler']->id;
        }
        if (isset($this->info['voter'])) {
            $cond[] = 'exists (select 1 from request_vote rv where rv.id_request = r.id_request and rv.id_user = ?)';
            $args[] = $this->info['voter']->id;
        }
        if (isset($this->info['category'])) {
            $cond[] = 'r.id_category in (' . placeholders($this->info['category']) . ')';
            array_push($args, ...$this->info['category']);
        }
        if (isset($this->info['release_type'])) {
            $cond[] = 'r.id_release_type in (' . placeholders($this->info['release_type']) . ')';
            array_push($args, ...$this->info['release_type']);
        }
        if (isset($this->info['encoding'])) {
            ['list' => $list, 'strict' => $strict] = $this->info['encoding'];
            $cond[] = $strict
                ? "encoding_str is not null and encoding_str && ARRAY[" . placeholders($list) . ']'
                : "(encoding_str is null or encoding_str && ARRAY[" . placeholders($list) . '])';
            array_push($args, ...array_map(fn ($key) => ENCODING[$key] ?? $key, $list));
        }
        if (isset($this->info['format'])) {
            ['list' => $list, 'strict' => $strict] = $this->info['format'];
            $cond[] = $strict
                ? "format_str is not null and format_str && ARRAY[" . placeholders($list) . ']'
                : "(format_str is null or format_str && ARRAY[" . placeholders($list) . '])';
            array_push($args, ...array_map(fn ($key) => FORMAT[$key] ?? $key, $list));
        }
        if (isset($this->info['media'])) {
            ['list' => $list, 'strict' => $strict] = $this->info['media'];
            $cond[] = $strict
                ? "media_str is not null and media_str && ARRAY[" . placeholders($list) . ']'
                : "(media_str is null or media_str && ARRAY[" . placeholders($list) . '])';
            array_push($args, ...array_map(fn ($key) => MEDIA[$key] ?? $key, $list));
        }
        if (isset($this->info['search'])) {
            $cond[] = "r.artist_title_ts @@ websearch_to_tsquery('simple', unaccent(?))";
            $args[] = $this->info['search'];
        }
        if (isset($this->info['tag'])) {
            $op = $this->info['tag']['mode'] == SearchTag::all ? '@>' : '&&';
            if (count($this->info['tag']['include']) > 0) {
                $list = array_map(fn ($t) => $t->id, $this->info['tag']['include']);
                $cond[] = "r.tag $op ARRAY[" . placeholders($list, '?::int') . "]";
                array_push($args, ...$list);
            }
            if (count($this->info['tag']['exclude']) > 0) {
                $list = array_map(fn ($t) => $t->id, $this->info['tag']['exclude']);
                $cond[] = "not r.tag $op ARRAY[" . placeholders($list, '?::int') . "]";
                array_push($args, ...$list);
            }
        }
        if (isset($this->info['year'])) {
            $cond[] = 'r.year = ?';
            $args[] = $this->info['year'];
        }
        if (!isset($this->info['show_filled'])) {
            $cond[] = 'r.id_torrent is null';
        }
        if (in_array($this->heading()->orderBy(), ['rvs.bounty_total', 'rvs.last_vote', 'rvs.user_total'])) {
            $join[] = 'inner join request_vote_summary rvs using (id_request)';
        }

        $this->cond = $join === []
            ? ''
            : ' ' . implode(' ', $join);
        if ($cond !== []) {
            $this->cond .= " where " . implode(' and ', $cond);
        }
        $this->args = $args;
        return $this->cond;
    }

    public function totalSql(): string {
        return "select count(*) from request r{$this->filter()}";
    }

    public function total(): int {
        return (int)$this->pg()->scalar(
            $this->totalSql(),
            ...$this->args,
        );
    }

    public function pageSql(): string {
        return "select r.id_request from request r{$this->filter()} order by {$this->heading()->orderBy()} {$this->heading()->dir()}, r.id_request {$this->heading()->dir()} limit ? offset ?";
    }

    public function page(int $limit, int $offset): array {
        return array_map(
            fn ($id) => $this->manager->findById($id),
            $this->pg()->column(
                $this->pageSql(),
                ...[...$this->args, $limit, $offset],
            )
        );
    }

    public function encodingList(): array {
        return $this->info['encoding'] ?? [];
    }

    public function formatList(): array {
        return $this->info['format'] ?? [];
    }

    public function mediaList(): array {
        return $this->info['media'] ?? [];
    }

    public function releaseTypeList(): array {
        return $this->info['release_type'] ?? [];
    }

    public function tagList(): array {
        if (!isset($this->info['tag'])) {
            return ['list' => '', 'mode' => SearchTag::any];
        }
        return [
            'list' => implode(',', [
                ...array_map(fn ($t) => $t->name(), $this->info['tag']['include']),
                ...array_map(fn ($t) => "!{$t->name()}", $this->info['tag']['exclude']),
            ]),
            'mode' => $this->info['tag']['mode'],
        ];
    }
}
