<?php

namespace Gazelle\Search;

class Request extends \Gazelle\Base {
    protected bool $negate;
    protected int $total;
    protected int $bookmarkerId;
    protected string $text;
    protected string $title;
    protected string $tagList;
    protected array $encodingList;
    protected array $formatList;
    protected array $mediaList;
    protected array $releaseTypeList;
    protected array $list;
    protected \SphinxqlQuery $sphinxq;

    public function __construct(
        protected \Gazelle\Manager\Request $manager,
    ) {
        $this->sphinxq = new \SphinxqlQuery();
    }

    public function isBookmarkView(): bool {
        return isset($this->bookmarkerId);
    }

    public function setBookmarker(\Gazelle\User $user): static {
        $this->text         = "{$user->username()} › Bookmarked requests";
        $this->title        = "{$user->link()} › Bookmarked requests";
        $this->bookmarkerId = $user->id();
        return $this;
    }

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
        if ($term) {
            $this->sphinxq->where('categoryid', $term);
        }
        return $this;
    }

    public function setCreator(\Gazelle\User $user): static {
        $this->text = "{$user->username()} › Requests created";
        $this->title = "{$user->link()} › Requests created";
        $this->sphinxq->where('userid', $user->id());
        return $this;
    }

    public function setFiller(\Gazelle\User $user): static {
        $this->text = "{$user->username()} › Requests filled";
        $this->title = "{$user->link()} › Requests filled";
        $this->sphinxq->where('fillerid', $user->id());
        return $this;
    }

    public function setEncoding(array $encodingList, bool $strict): static {
        if (in_array(count($encodingList), [0, count(ENCODING)])) {
            return $this;
        }
        [$args, $term] = $this->setResourceFilter(ENCODING, $encodingList, $strict);
        $this->encodingList = $args;
        $this->negate      = true;
        $this->sphinxq->where_match(implode(' ', $term), 'bitratelist', false);
        return $this;
    }

    public function setFormat(array $formatList, bool $strict): static {
        if (in_array(count($formatList), [0, count(FORMAT)])) {
            return $this;
        }
        [$args, $term] = $this->setResourceFilter(FORMAT, $formatList, $strict);
        $this->formatList = $args;
        $this->negate     = true;
        $this->sphinxq->where_match(implode(' ', $term), 'formatlist', false);
        return $this;
    }

    public function setMedia(array $mediaList, bool $strict): static {
        if (in_array(count($mediaList), [0, count(MEDIA)])) {
            return $this;
        }
        [$args, $term] = $this->setResourceFilter(MEDIA, $mediaList, $strict);
        $this->mediaList = $args;
        $this->negate    = true;
        $this->sphinxq->where_match(implode(' ', $term), 'medialist', false);
        return $this;
    }

    /**
     * @return array<array>
     */
    protected function setResourceFilter(array $source, array $selected, bool $strict): array {
        $args = [];
        $term = [];
        if (!$strict) {
            $term[] = 'any';
        }
        foreach ($source as $idx => $value) {
            if (in_array($idx, $selected)) {
                $args[] = $idx;
                $term[] = strtolower($value);
            }
        }
        $term = [
            '(',
            implode(
                ' | ',
                array_map(fn ($t) => "\"$t\"", $term)
            ),
            ')'
        ];
        if ($strict) {
            foreach ($source as $idx => $value) {
                if (!in_array($idx, $selected)) {
                    $term[] = '-"' . strtolower($value) . '"';
                }
            }
        }
        return [$args, $term];
    }

    public function setReleaseType(array $releaseTypeList, array $allReleaseType): static {
        if (in_array(count($releaseTypeList), [0, count($allReleaseType)])) {
            return $this;
        }
        $term = [];
        foreach ($releaseTypeList as $idx) {
            if (isset($allReleaseType[$idx])) {
                $term[] = $idx;
            }
        }
        if ($term) {
            $this->releaseTypeList = $term;
            $this->sphinxq->where('releasetype', $term);
        }
        return $this;
    }

    public function setRequestor(int $requestor): static {
        $this->sphinxq->where('userid', $requestor);
        return $this;
    }

    public function setTag(string $tagList, string $tagMode): static {
        $tagList = str_replace('.', '_', trim($tagList));
        if ($tagList === '') {
            return $this;
        }
        $include = [];
        $exclude = [];
        $split = preg_split('/\s*,\s*/', $tagList);
        if ($split === false) {
            return $this;
        }
        foreach ($split as $tag) {
            if (preg_match('/^(![^!]+)$/', $tag, $match)) {
                $exclude[] = $match[1];
            } else {
                $include[] = $tag;
                $this->negate = true;
            }
        }
        $filter = (new \Gazelle\Manager\Tag())->sphinxFilter(['include' => $include, 'exclude' => $exclude], $this->negate, $tagMode === 'all');
        $this->tagList = $filter['input'];
        if ($filter['predicate']) {
            $this->sphinxq->where_match($filter['predicate'], 'taglist', false);
        }
        return $this;
    }

    public function setText(string $text): static {
        $text = trim($text);
        if ($text === '') {
            return $this;
        }
        $include = [];
        $exclude = [];
        $nrTerms = 0;
        $split = preg_split('/\s+/', $text);
        if ($split === false) {
            return $this;
        }
        foreach ($split as $term) {
            // Skip isolated hyphens to enable "Artist - Title" searches
            if (in_array($term, ['-', '–'])) {
                continue;
            }
            ++$nrTerms;
            if (preg_match('/^!([^!]+)$/', $term, $match)) {
                $exclude[] = '!' . \Sphinxql::sph_escape_string($match[1]);
            } else {
                $include[] = \Sphinxql::sph_escape_string($term);
                $this->negate = true;
            }
        }
        if ($nrTerms === 0) {
            return $this;
        }

        $queryTerm = $include;
        if (isset($this->negate) && $exclude) {
            $queryTerm = [...$queryTerm, ...$exclude];
        }
        if ($queryTerm) {
            $this->sphinxq->where_match(implode(' ', $queryTerm), '*', false);
        }
        return $this;
    }

    public function setVisible(bool $truth): static {
        $this->sphinxq->where('visible', (int)$truth);
        return $this;
    }

    public function setVoter(\Gazelle\User $user): static {
        $this->text = "{$user->username()} › Requests voted on";
        $this->title = "{$user->link()} › Requests voted on";
        $this->sphinxq->where('voter', $user->id());
        return $this;
    }

    public function setYear(int $year): static {
        $this->sphinxq->where('year', $year);
        return $this;
    }

    public function showUnfilled(): static {
        $this->sphinxq->where('torrentid', 0);
        return $this;
    }

    public function limit(int $offset, int $limit, int $end): static {
        $this->sphinxq->limit($offset, $limit, $end);
        return $this;
    }

    public function execute(string $orderBy, string $direction): int {
        if (isset($this->bookmarkerId)) {
            switch ($orderBy) {
                case 'bounty':
                    $needVoteTable = true;
                    $orderBy       = 'sum(rv.Bounty)';
                    break;
                case 'filled':
                    $needVoteTable = false;
                    $orderBy       = 'if(r.TorrentID = 0, 1, 0)';
                    break;
                case 'lastvote':
                    $needVoteTable = false;
                    $orderBy       = 'r.LastVote';
                    break;
                case 'votes':
                    $needVoteTable = true;
                    $orderBy       = 'count(rv.RequestID)';
                    break;
                default:
                    $needVoteTable = false;
                    $orderBy       = 'br.Time';
                    break;
            }
            if ($needVoteTable) {
                $voteJoin = 'INNER JOIN requests_votes rv USING (RequestID)';
                $groupBy  = 'GROUP BY r.ID';
            } else {
                $voteJoin = '';
                $groupBy  = '';
            }
            self::$db->prepared_query("
                SELECT r.ID
                FROM requests r
                INNER JOIN bookmarks_requests br ON (br.RequestID = r.ID)
                $voteJoin
                WHERE br.UserID = ?
                $groupBy
                ORDER BY $orderBy $direction
                ", $this->bookmarkerId
            );
            $list = self::$db->collect(0, false);
            $this->total = count($list);
        } else {
            $this->sphinxq->select('id')->from('requests, requests_delta');
            $this->sphinxq->order_by($orderBy, $direction);
            $result      = $this->sphinxq->sphinxquery();
            if ($result !== false) {
                $this->total = (int)$result->get_meta('total_found');
                $list = array_keys($result->to_array('id'));
            } else {
                $this->total = 0;
                $list = [];
            }
        }
        $this->list = array_map(fn ($id) => $this->manager->findById($id), $list);
        return count($this->list);
    }

    public function list(): array {
        return $this->list ?? [];
    }

    public function total(): int {
        return $this->total ?? 0;
    }

    public function encodingList(): array {
        return $this->encodingList ?? [];
    }

    public function formatList(): array {
        return $this->formatList ?? [];
    }

    public function mediaList(): array {
        return $this->mediaList ?? [];
    }

    public function releaseTypeList(): array {
        return $this->releaseTypeList ?? [];
    }

    public function tagList(): string {
        return $this->tagList ?? '';
    }

    public function text(): string {
        return $this->text ?? 'Requests';
    }

    public function title(): string {
        return $this->title ?? 'Requests';
    }
}
