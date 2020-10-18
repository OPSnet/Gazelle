<?php

namespace Gazelle;

class ForumSearch extends Base {
    protected $user;
    protected $permittedForums = [];
    protected $forbiddenForums = [];
    protected $selectedForums = [];
    protected $forumCond = [];
    protected $forumArgs = [];
    protected $threadCond = [];
    protected $threadArgs = [];
    protected $postCond = [];
    protected $postArgs = [];

    protected $threadTitleSearch = true;
    protected $searchText = '';
    protected $authorName = '';
    protected $authorId = '';
    protected $page = 0;
    protected $threadId;
    protected $linkbox;

    public function __construct(User $user) {
        parent::__construct();
        $this->user = $user;
        $this->permittedForums = $this->user->permittedForums();
        $this->forbiddenForums = $this->user->forbiddenForums();
    }

    public function searchText(): string {
        return $this->searchText;
    }

    public function authorName(): string {
        return $this->authorName;
    }

    public function threadId(): int {
        return $this->threadId;
    }

    /**
     * Set search mode (by topic title or post body)
     *
     * @param string mode (if 'body' then search in post bodies, anything else defaults to thread title)
     */
    public function setSearchType(string $mode) {
        $this->threadTitleSearch = ($mode !== 'body');
        return $this;
    }

    /**
     * Are we searching in the bodies of posts?
     *
     * @return true is this is a body search
     */
    public function isBodySearch(): bool {
        // all that for one lousy pun
        return !$this->threadTitleSearch;
    }

    /**
     * Set search text (will be split on whitespace into multiple words)
     *
     * @param string text
     */
    public function setSearchText(string $searchText) {
        $this->searchText = $searchText;
        return $this;
    }

    /**
     * Set name of author to search for
     *
     * @param string username
     */
    public function setAuthor(string $username) {
        $this->authorName = trim($username);
        if (!empty($this->authorName)) {
            $this->authorId = $this->db->scalar("
                SELECT ID FROM users_main WHERE Username = ?
                ", $this->authorName
            );
        }
        return $this;
    }

    /**
     * Set the list of forum IDs within which to search
     *
     * @param array list of forum IDs
     */
    public function setForumList(array $list) {
        $this->selectedForums = [];
        foreach ($list as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $this->selectedForums[] = $id;
            }
        }
    }

    /**
     * Save a condition related to forums
     *
     * @param string The SQL condition with one or more placeholders
     * @param mixed The values to bind to the placeholders
     */
    protected function setForumCond(string $condition, $arg) {
        $this->forumCond[] = $condition;
        if (is_array($arg)) {
            $this->forumArgs = array_merge($this->forumArgs, $arg);
        } else {
            $this->forumArgs[] = $arg;
        }
        return $this;
    }

    /**
     * Save a condition related to thread searches
     *
     * @param string The SQL condition with one or more placeholders
     * @param mixed The values to bind to the placeholders
     */
    protected function setThreadCond(string $condition, $arg) {
        $this->threadCond[] = $condition;
        if (is_array($arg)) {
            $this->threadArgs = array_merge($this->threadArgs, $arg);
        } else {
            $this->threadArgs[] = $arg;
        }
        return $this;
    }

    /**
     * Limit the search to threads created before this date
     *
     * @param string date (yyyy/mm/dd)
     */
    public function setThreadCreatedBefore(string $date) {
        return $this->setThreadCond('t.CreatedTime <= ?', $date);
    }

    /**
     * Limit the search to threads created after this date
     *
     * @param string date (yyyy/mm/dd)
     */
    public function setThreadCreatedAfter(string $date) {
        return $this->setThreadCond('t.CreatedTime >= ?', $date);
    }

    /**
     * Save a condition related to post searches
     *
     * @param string The SQL condition with one or more placeholders
     * @param mixed The values to bind to the placeholders
     */
    protected function setPostCond(string $condition, $arg) {
        $this->postCond[] = $condition;
        if (is_array($arg)) {
            $this->postArgs = array_merge($this->postArgs, $arg);
        } else {
            $this->postArgs[] = $arg;
        }
        return $this;
    }

    /**
     * Limit the search to posts created before this date
     *
     * @param string date (yyyy/mm/dd)
     */
    public function setPostCreatedBefore(string $date) {
        return $this->setPostCond('p.AddedTime <= ?', $date);
    }

    /**
     * Limit the search to threads created after this date
     *
     * @param string date (yyyy/mm/dd)
     */
    public function setPostCreatedAfter(string $date) {
        return $this->setPostCond('p.AddedTime >= ?', $date);
    }

    // SEARCH METHODS

    /**
     * Prepare the query parameters of a forum query to allow a user to access
     * only the forums to which they are allowed according to site rules,
     * along with any other filters that have been initialized.
     *
     * @param bool Don't configure the full text search (it may cause thread title lookups to fail)
     *
     * @return array [sql conditions to bind, bind arguments]
     */
    protected function configure($configureWords = true): array {
        $cond = array_merge($this->forumCond, $this->threadCond, $this->isBodySearch() ? $this->postCond : []);
        $args = array_merge($this->forumArgs, $this->threadArgs, $this->isBodySearch() ? $this->postArgs : []);
        if (!($this->permittedForums || $this->selectedForums)) {
            // any forum they have access to due to their class
            $cond[] = 'f.MinClassRead <= ?';
            $args[] = $this->user->primaryClass();
        } else {
            if ($this->selectedForums) {
                $cond[] = 'f.ID in (' . placeholders($this->selectedForums) . ')';
                $args = array_merge($args, $this->selectedForums);
            }

            $cond[] = '(f.MinClassRead <= ?' . ($this->permittedForums ? ' OR f.ID IN (' . placeholders($this->permittedForums) . ')' : '') . ')';
            $args[] = array_merge([$this->user->primaryClass()], $this->permittedForums);
        }
        // but not if they have been banned from it
        if ($this->forbiddenForums) {
            $cond[] = 'f.ID NOT IN (' . placeholders($this->forbiddenForums) . ')';
            $args = array_merge($args, $this->forbiddenForums);
        }
        // full text search needed?
        $words = array_unique(explode(' ', $this->searchText));
        if (!$configureWords || !$words) {
            return [$cond, $args];
        }
        $args = array_merge($args, $words);
        $cond = array_merge($cond,
            array_fill(
                0, count($words),
                ($this->isBodySearch() ? "p.Body" : "t.Title") . " LIKE concat('%', ?, '%')"
            )
        );
        return [$cond, $args];
    }

    /**
     * Get the title of the thread within which the user is searching,
     * taking into account whether they are allowed to search in threads (permitted/forbidden)
     */
    public function threadTitle(int $threadId): string {
        [$cond, $args] = $this->configure(false);
        $cond[] = 't.ID = ?';
        $args[] = $threadId;
        $forumPostJoin = $this->isBodySearch() ? 'INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)' : '';
        return $this->db->scalar("
            SELECT Title
            FROM forums_topics AS t
            INNER JOIN forums AS f ON (f.ID = t.ForumID)
            WHERE " . implode(' AND ', $cond), ...$args
        );
    }

    /**
     * Return the total number of rows the unpaginated query would return
     * (so that we can set up pagination links)
     *
     * @param int number of rows
     */
    public function totalHits(): int {
        [$cond, $args] = $this->configure();
        $forumPostJoin = $this->isBodySearch() ? 'INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)' : '';
        return $this->db->scalar("
            SELECT count(*)
            FROM forums AS f
            INNER JOIN forums_topics AS t ON (t.ForumID = f.ID) $forumPostJoin
            WHERE " . implode(' AND ', $cond), ...$args
        );
    }

    /**
     * Return a paginated section of the result set
     *
     * @param array a collection of results
     */
    public function results(array $pageLimit): array {
        [$cond, $args] = $this->configure();
        $forumPostJoin = $this->isBodySearch() ? 'INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)' : '';
        [$this->page, $limit] = $pageLimit;
        if ($this->isBodySearch()) {
            $sql = "SELECT t.ID,"
                . ($this->threadId ? "substring_index(p.Body, ' ', 40)" : 't.Title') . ",
                t.ForumID,
                f.Name,
                p.AddedTime,
                p.ID,
                p.Body,
                t.CreatedTime
            FROM forums AS f
            INNER JOIN forums_topics AS t ON (t.ForumID = f.ID) $forumPostJoin
            WHERE " . implode(' AND ', $cond) . "
            ORDER BY p.AddedTime DESC
            LIMIT ?";
        } else {
            $sql = "SELECT t.ID,
                t.Title,
                t.ForumID,
                f.Name,
                t.LastPostTime,
                '',
                '',
                t.CreatedTime
            FROM forums AS f
            INNER JOIN forums_topics AS t ON (t.ForumID = f.ID) $forumPostJoin
            WHERE " . implode(' AND ', $cond) . "
            ORDER BY t.LastPostTime DESC
            LIMIT ?";
        }
        $args[] = $limit;
        $this->db->prepared_query($sql, ...$args);
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    /**
     * Display the HTML linkbox of the result set
     *
     * @return string HTML page linkbox
     */
    public function pageLinkbox(): string {
        if (is_null($this->linkbox)) {
            $this->linkbox = \Format::get_pages($this->page, $this->totalHits(), POSTS_PER_PAGE, 9) ?: '';
        }
        return $this->linkbox;
    }
}
