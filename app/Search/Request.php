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
        if (!isset($this->info['show_filled'])) {
            $cond[] = 'r.id_torrent is null';
        }
        if (isset($this->info['bookmarker'])) {
            $join[] = 'inner join relay.bookmarks_requests rb on (rb."RequestID" = r.id_request)';
            $cond[] = 'rb."UserID" = $' . (1 + count($args));
            $args[] = $this->info['bookmarker']->id;
        }
        if (isset($this->info['creator'])) {
            $cond[] = 'r.id_user = $' . (1 + count($args));
            $args[] = $this->info['creator']->id;
        }
        if (isset($this->info['filler'])) {
            $cond[] = 'r.id_filler = $' . (1 + count($args));
            $args[] = $this->info['filler']->id;
        }
        if (isset($this->info['year'])) {
            $cond[] = 'r.year = $' . (1 + count($args));
            $args[] = $this->info['year'];
        }
        if (isset($this->info['voter'])) {
            $cond[] = 'exists (select 1 from request_vote rv where rv.id_request = r.id_request and rv.id_user = $'
                . (1 + count($args))
                . ')';
            $args[] = $this->info['voter']->id;
        }
        if (isset($this->info['category'])) {
            $cond[] = 'r.id_category = any($' . (1 + count($args)) . '::int[])';
            $args[] = $this->pg()->integerConverter()->output($this->info['category']);
        }
        if (isset($this->info['release_type'])) {
            $cond[] = 'r.id_release_type = any($' . (1 + count($args)) . '::int[])';
            $args[] = $this->pg()->integerConverter()->output($this->info['release_type']);
        }
        if (isset($this->info['encoding'])) {
            $placeholder = '$' . (1 + count($args));
            $cond[] = $this->info['encoding']['strict']
                ? "encoding_str && $placeholder::text[]"
                : "(encoding_str is null or encoding_str && $placeholder::text[])";
            $args[] = $this->pg()->arrayConverter()->output(
                array_map(fn ($key) => ENCODING[$key] ?? $key, $this->info['encoding']['list'])
            );
        }
        if (isset($this->info['format'])) {
            $placeholder = '$' . (1 + count($args));
            $cond[] = $this->info['format']['strict']
                ? "format_str && $placeholder::text[]"
                : "(format_str is null or format_str && $placeholder::text[])";
            $args[] = $this->pg()->arrayConverter()->output(
                array_map(fn ($key) => FORMAT[$key] ?? $key, $this->info['format']['list'])
            );
        }
        if (isset($this->info['media'])) {
            $placeholder = '$' . (1 + count($args));
            $cond[] = $this->info['media']['strict']
                ? "media_str && $placeholder"
                : "(media_str is null or media_str && $placeholder)";
            $args[] = $this->pg()->arrayConverter()->output(
                array_map(fn ($key) => MEDIA[$key] ?? $key, $this->info['media']['list'])
            );
        }
        if (isset($this->info['search'])) {
            $cond[] = "r.artist_title_ts @@ websearch_to_tsquery('simple', unaccent($"
                . (1 + count($args))
                . '))';
            $args[] = $this->info['search'];
        }
        if (isset($this->info['tag'])) {
            $op = $this->info['tag']['mode'] == SearchTag::all ? '@>' : '&&';
            if (count($this->info['tag']['include']) > 0) {
                $cond[] = "r.tag $op $" . (1 + count($args)) . "::int[]";
                $args[] = $this->pg()->integerConverter()->output(
                    array_map(
                        fn ($t) => $t->id,
                        $this->info['tag']['include']
                    )
                );
            }
            if (count($this->info['tag']['exclude']) > 0) {
                $cond[] = "not r.tag $op $" . (1 + count($args)) . "::int[]";
                $args[] = $this->pg()->integerConverter()->output(
                    array_map(
                        fn ($t) => $t->id,
                        $this->info['tag']['exclude']
                    )
                );
            }
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
        return (int)$this->pg()->scalarex(
            $this->totalSql(),
            ...$this->args,
        );
    }

    public function pageSql(): string {
        return "select r.id_request from request r{$this->filter()} order by {$this->heading()->orderBy()} {$this->heading()->dir()}, r.id_request {$this->heading()->dir()} limit $"
            . (count($this->args) + 1)
            . ' offset $'
            . (count($this->args) + 2);
    }

    public function page(int $limit, int $offset): array {
        return array_map(
            fn ($id) => $this->manager->findById($id),
            $this->pg()->columnex(
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
