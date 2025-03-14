<?php

namespace Gazelle\Search;

class Forum extends \Gazelle\BaseUser {
    final public const tableName = 'forums';

    protected array $permittedForums = [];
    protected array $forbiddenForums = [];
    protected array $selectedForums = [];
    protected array $forumCond = [];
    protected array $forumArgs = [];
    protected array $threadCond = [];
    protected array $threadArgs = [];
    protected array $postCond = [];
    protected array $postArgs = [];

    protected string $searchText = '';
    protected string $authorName = '';

    protected int $authorId = 0;
    protected int $page = 0;
    protected int $threadId;
    protected int $total = 0;

    protected bool $threadTitleSearch = true;
    protected bool $splitWords = false;
    protected bool $showGrouped = false;
    protected bool $showUnread = true;

    protected \Gazelle\User $viewer;

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    public function link(): string {
        return $this->user()->link();
    }

    public function location(): string {
        return $this->user()->location();
    }

    public function __construct(\Gazelle\User $user) {
        parent::__construct($user);
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

    public function setViewer(\Gazelle\User $viewer): static {
        $this->viewer = $viewer;
        $this->permittedForums = $this->viewer->permittedForums();
        $this->forbiddenForums = $this->viewer->forbiddenForums();
        return $this;
    }

    /**
     * Set search mode (by topic title or post body)
     *
     * @param string $mode (if 'body' then search in post bodies, anything else defaults to thread title)
     */
    public function setSearchType(string $mode): static {
        $this->threadTitleSearch = ($mode !== 'body');
        return $this;
    }

    /**
     * Are we searching in the bodies of posts?
     *
     * @return bool is this is a body search
     */
    public function isBodySearch(): bool {
        // all that for one lousy pun
        return !$this->threadTitleSearch;
    }

    /**
     * Set search text (will be split on whitespace into multiple words)
     */
    public function setSearchText(string $searchText): static {
        $this->searchText = $searchText;
        return $this;
    }

    /**
     * Set name of author to search for. If the author does not exist, the
     * search will be for user_id = 0, which amounts to the same thing.
     */
    public function setAuthor(string $username): static {
        $this->authorName = trim($username);
        if (!empty($this->authorName)) {
            $this->authorId = (int)self::$db->scalar("
                SELECT ID FROM users_main WHERE Username = ?
                ", $this->authorName
            );
        }
        return $this->isBodySearch()
            ? $this->setPostCond('p.AuthorID = ?', $this->authorId)
            : $this->setThreadCond('t.AuthorID = ?', $this->authorId);
    }

    /**
     * Set the list of forum IDs within which to search
     */
    public function setForumList(array $list): static {
        $this->selectedForums = [];
        foreach ($list as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $this->selectedForums[] = $id;
            }
        }
        return $this;
    }

    /**
     * When searching on post history, will all posts in a thread be grouped?
     */
    public function setShowGrouped(bool $showGrouped): static {
        $this->showGrouped = $showGrouped;
        return $this;
    }

    /**
     * When searching on post history, are only unread posts wanted?
     */
    public function setShowUnread(bool $showUnread): static {
        $this->showUnread = $showUnread;
        return $this;
    }

    /**
     * Save a condition related to forums
     */
    protected function setForumCond(string $condition, $arg): static {
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
     */
    protected function setThreadCond(string $condition, $arg): static {
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
     */
    public function setThreadCreatedBefore(string $date): static {
        return $this->setThreadCond('t.CreatedTime <= ?', $date);
    }

    /**
     * Limit the search to threads created after this date
     */
    public function setThreadCreatedAfter(string $date): static {
        return $this->setThreadCond('t.CreatedTime >= ?', $date);
    }

    public function setThreadId(int $threadId): static {
        $this->threadId = $threadId;
        return $this->setThreadCond('t.ID = ?', $this->threadId);
    }

    /**
     * Save a condition related to post searches
     */
    protected function setPostCond(string $condition, $arg): static {
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
     */
    public function setPostCreatedBefore(string $date): static {
        return $this->setPostCond('p.AddedTime <= ?', $date);
    }

    /**
     * Limit the search to threads created after this date
     */
    public function setPostCreatedAfter(string $date): static {
        return $this->setPostCond('p.AddedTime >= ?', $date);
    }

    // SEARCH METHODS

    /**
     * Do we need to split the search words for a full text search?
     */
    public function setSplitWords(bool $splitWords): static {
        $this->splitWords = $splitWords;
        return $this;
    }

    /**
     * Prepare the query parameters of a forum query to allow a user to access
     * only the forums to which they are allowed according to site rules,
     * along with any other filters that have been initialized.
     *
     * @return array [sql conditions to bind, bind arguments]
     */
    protected function configure(): array {
        $cond = array_merge($this->forumCond, $this->threadCond, $this->isBodySearch() ? $this->postCond : []);
        $args = array_merge($this->forumArgs, $this->threadArgs, $this->isBodySearch() ? $this->postArgs : []);
        $userContext = $this->viewer ?? $this->user;
        if (!($this->permittedForums || $this->selectedForums)) {
            // any forum they have access to due to their class
            $cond[] = 'f.MinClassRead <= ?';
            $args[] = $userContext->classLevel();
        } else {
            if ($this->selectedForums) {
                $cond[] = 'f.ID in (' . placeholders($this->selectedForums) . ')';
                $args = array_merge($args, $this->selectedForums);
            }

            $cond[] = '(f.MinClassRead <= ?' . ($this->permittedForums ? ' OR f.ID IN (' . placeholders($this->permittedForums) . ')' : '') . ')';
            $args = array_merge($args, [$userContext->classLevel()], $this->permittedForums);
        }
        // but not if they have been banned from it
        if ($this->forbiddenForums) {
            $cond[] = 'f.ID NOT IN (' . placeholders($this->forbiddenForums) . ')';
            $args = array_merge($args, $this->forbiddenForums);
        }
        // full text search needed?
        $words = array_unique(explode(' ', $this->searchText));
        if ($this->splitWords) {
            $args = array_merge($args, $words);
            $cond = array_merge($cond,
                array_fill(
                    0, count($words),
                    ($this->isBodySearch() ? "p.Body" : "t.Title") . " LIKE concat('%', ?, '%')"
                )
            );
        }
        return [$cond, $args];
    }

    protected function configurePostHistory(): array {
        [$cond, $args] = $this->configure();
        $from = "FROM forums_posts AS p
            LEFT JOIN forums_topics AS t ON (t.ID = p.TopicID)
            LEFT JOIN forums AS f ON (f.ID = t.ForumID)
            LEFT JOIN forums_last_read_topics AS flrt ON (flrt.TopicID = t.ID AND flrt.UserID = ?)";
        $cond[] = 'p.AuthorID = ?';
        $args[] = $this->user->id();
        array_unshift($args, $this->user->id());
        if ($this->showUnread) {
            $cond[] = "(t.IsLocked = '0' OR t.IsSticky = '1') AND (flrt.PostID < t.LastPostID OR flrt.PostID IS NULL)";
        }
        return [$from, $cond, $args];
    }

    /**
     * Get the title of the thread within which the user is searching,
     * taking into account whether they are allowed to search in threads (permitted/forbidden)
     */
    public function threadTitle(int $threadId): ?string {
        [$cond, $args] = $this->configure();
        $cond[] = 't.ID = ?';
        $args[] = $threadId;
        $forumPostJoin = $this->isBodySearch() ? 'INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)' : '';
        return (string)self::$db->scalar("
            SELECT Title
            FROM forums_topics AS t
            INNER JOIN forums AS f ON (f.ID = t.ForumID) $forumPostJoin
            WHERE " . implode(' AND ', $cond), ...$args
        );
    }

    /**
     * Return the total number of rows the unpaginated query would return
     * (so that we can set up pagination links)
     */
    public function totalHits(): int {
        [$cond, $args] = $this->setSplitWords(true)->configure();
        $forumPostJoin = $this->isBodySearch() ? 'INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)' : '';
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM forums AS f
            INNER JOIN forums_topics AS t ON (t.ForumID = f.ID) $forumPostJoin
            WHERE " . implode(' AND ', $cond), ...$args
        );
    }

    /**
     * Return a paginated section of the result set
     */
    public function results(\Gazelle\Util\Paginator $paginator): array {
        [$cond, $args] = $this->setSplitWords(true)->configure();
        $forumPostJoin = $this->isBodySearch() ? 'INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)' : '';
        if ($this->isBodySearch()) {
            $sql = "SELECT t.ID,"
                . (isset($this->threadId) ? "substring_index(p.Body, ' ', 40)" : 't.Title') . ",
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
            LIMIT ? OFFSET ?";
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
            LIMIT ? OFFSET ?";
        }
        $this->page = $paginator->page();
        array_push($args, $paginator->limit(), $paginator->offset());
        self::$db->prepared_query($sql, ...$args);
        return self::$db->to_array(false, MYSQLI_NUM, false);
    }

    /**
     * How many threads has a user created?
     *
     * @return int number of threads
     */
    public function threadsByUserTotal(): int {
        [$cond, $args] = $this->configure();
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM forums AS f
            INNER JOIN forums_topics AS t ON (t.ForumID = f.ID)
            WHERE t.AuthorID = ?
                AND " . implode(' AND ', $cond),
            $this->user->id(), ...$args
        );
    }

    /**
     * Return a list of threads created by a user
     *
     * @return array of [thread_id, thread_title, created_time, last_post_time, forum_id, forum_title]
     */
    public function threadsByUserPage(int $limit, int $offset): array {
        [$cond, $args] = $this->configure();
        $args[] = $limit;
        $args[] = $offset;
        self::$db->prepared_query("
            SELECT t.ID        AS thread_id,
                t.Title        AS thread_title,
                t.CreatedTime  AS created_time,
                t.LastPostTime AS last_post_time,
                f.ID           AS forum_id,
                f.Name         AS forum_title
            FROM forums AS f
            INNER JOIN forums_topics AS t ON (t.ForumID = f.ID)
            WHERE t.AuthorID = ?
                AND " . implode(' AND ', $cond) . "
            ORDER BY t.ID DESC
            LIMIT ? OFFSET ?
            ", $this->user->id(), ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function postHistoryTotal(): int {
        [$from, $cond, $args] = $this->configurePostHistory();
        return (int)self::$db->scalar("
            SELECT count(*)
            $from
            WHERE
            " . implode(' AND ', $cond),
            ...$args
        );
    }

    public function postHistoryPage(int $limit, int $offset): array {
        [$from, $cond, $args] = $this->configurePostHistory();
        $args[] = $limit;
        $args[] = $offset;
        $unreadFirst = $this->showUnread ? 'flrt.PostID AS last_read,' : '';
        self::$db->prepared_query("
            SELECT p.ID         AS post_id,
                p.AddedTime     AS added_time,
                p.Body          AS body,
                p.EditedUserID  AS edited_user_id,
                p.EditedTime    AS edited_time,
                p.TopicID       AS thread_id,
                t.Title         AS title,
                t.IsLocked      AS is_locked,
                t.IsSticky      AS is_sticky,
                t.LastPostID    AS last_post_id,
                $unreadFirst
                (NOT t.IsLocked OR t.IsSticky) AND (coalesce(flrt.PostID, 0) < t.LastPostID) as new
            $from
            WHERE
            " . implode(' AND ', $cond)
              . ($this->showGrouped ? ' GROUP BY t.ID' : '')
              . "
            ORDER BY p.ID DESC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
