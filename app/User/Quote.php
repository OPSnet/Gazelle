<?php

namespace Gazelle\User;

class Quote extends \Gazelle\BaseUser {
    final public const tableName = 'users_notify_quoted';
    final protected const UNREAD_QUOTE_KEY = 'u_unread_%d';

    protected bool $showAll = false;

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::UNREAD_QUOTE_KEY, $this->user->id()));
        return $this;
    }

    public function create(int $quoterId, string $page, int $pageId, int $postId): int {
        self::$db->prepared_query('
            INSERT IGNORE INTO users_notify_quoted
                   (UserID, QuoterID, Page, PageID, PostID)
            VALUES (?,      ?,        ?,    ?,      ?)
            ', $this->user->id(), $quoterId, $page, $pageId, $postId
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    /**
     * Toggle whether only unread quotes should be listed
     */
    public function setShowAll(bool $showAll): static {
        $this->showAll = $showAll;
        return $this;
    }

    /**
     * Are only unread quotes displayed?
     */
    public function showAll(): bool {
        return $this->showAll;
    }

    public function clearAll(): int {
        self::$db->prepared_query("
            UPDATE users_notify_quoted SET
                UnRead = false
            WHERE Unread = true
                AND UserID = ?
            ", $this->user->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    /**
     * Mark the user as having seen their quoted posts in a thread
     */
    public function clearThread(int $threadId, int $firstPost, int $lastPost): int {
        self::$db->prepared_query("
            UPDATE users_notify_quoted SET
                UnRead = false
            WHERE Page = 'forums'
                AND UserID = ?
                AND PageID = ?
                AND PostID BETWEEN ? AND ?
            ", $this->user->id(), $threadId, $firstPost, $lastPost
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    /**
     * Configure the conditions for querying the list of unread quotes
     *
     * NB: the functionality to check forum quotes also appears in the Search\Forum
     * class. It is not obvious how to hold the logic in one place only.
     *
     * @return array [array conditions, array arguments]
     * The conditions should be AND'ed together in a WHERE clause and the
     * arguments passed to a db query.
     */
    protected function configure(): array {
        $permittedForums = $this->user->permittedForums();
        $forbiddenForums = $this->user->forbiddenForums();
        $forumCond = [];
        $forumArgs = [];
        if (!$permittedForums) {
            // any forum they have access to due to their class
            $forumCond[] = 'f.MinClassRead <= ?';
            $forumArgs[] = $this->user->classLevel();
        } else {
            // any forum they have access to due to their class plus additional grants
            $forumCond[] = '(f.MinClassRead <= ? OR f.ID IN (' . placeholders($permittedForums) . '))';
            $forumArgs = array_merge([$this->user->classLevel()], $permittedForums);
        }
        if ($forbiddenForums) {
            // but not if they have been banned from it
            $forumCond[] = 'f.ID NOT IN (' . placeholders($forbiddenForums) . ')';
            $forumArgs = array_merge($forumArgs, $forbiddenForums);
        }

        $cond = [
            "q.UserID = ?",
            "(q.Page != 'collages' OR c.Deleted = '0')",
            "(q.Page != 'forums' OR " . join(' AND ', $forumCond) . ")",
        ];
        $args = array_merge(
            [$this->user->id()],
            $forumArgs,
        );

        if (!$this->showAll) {
            $cond[] = 'q.UnRead';
        }
        return [$cond, $args];
    }

    /**
     * How many quotes does the person have
     */
    public function total(): int {
        [$cond, $args] = $this->configure();
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM users_notify_quoted AS q
            LEFT JOIN forums_topics  AS t ON (t.ID = q.PageID)
            LEFT JOIN forums         AS f ON (f.ID = t.ForumID)
            LEFT JOIN artists_group  AS a ON (a.ArtistID = q.PageID)
            LEFT JOIN collages       AS c ON (c.ID = q.PageID)
            WHERE " . join(' AND ', $cond), ...$args
        );
    }

    /**
     * Get a page of quotes
     *
     * @param int $limit Number of quotes in the page (limit)
     * @param int $offset How far from the beginning of the list (offset)
     * @return array of quote results, each having
     *  jump: url that points to the quote' => "torrents.php?id={$q['PageID']}&amp;postid={$q['PostID']}#post{$q['PostID']}",
     *  link: html href that points to the context (artist, collage, forum, request, torrent)
     *  title: name of the context (Artist, Collage, ...)
     *  date: date the quote was made
     *  page: (artist, collage, forums, ...)
     *  quoter_id: user id
     *  unread: has the viewer seen the quote
     */
    public function page(int $limit, int $offset): array {
        [$cond, $args] = $this->configure();
        $args = array_merge($args, [$limit, $offset]);
        self::$db->prepared_query("
            SELECT q.Page,
                q.PageID,
                q.PostID,
                q.QuoterID,
                q.Date,
                q.UnRead,
                f.ID    AS ForumID,
                f.Name  AS ForumName,
                t.ID    AS threadId,
                t.Title AS ForumTitle,
                aa.Name AS ArtistName,
                c.Name  AS CollageName
            FROM users_notify_quoted AS q
            LEFT JOIN forums_topics  AS t ON (t.ID = q.PageID)
            LEFT JOIN forums         AS f ON (f.ID = t.ForumID)
            LEFT JOIN artists_group  AS a ON (a.ArtistID = q.PageID)
            INNER JOIN artists_alias   aa ON (a.PrimaryAlias = aa.AliasID)
            LEFT JOIN collages       AS c ON (c.ID = q.PageID)
            WHERE " . join(' AND ', $cond) . "
            ORDER BY q.Date DESC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        $quoteList = self::$db->to_array(false, MYSQLI_ASSOC, false);

        $page    = [];
        $postMan = new \Gazelle\Manager\ForumPost();
        $reqMan  = new \Gazelle\Manager\Request();
        $tgMan   = new \Gazelle\Manager\TGroup();

        foreach ($quoteList as $q) {
            $context = [];
            switch ($q['Page']) {
            case 'artist':
                $context = [
                    'jump'  => "artist.php?id={$q['PageID']}&amp;postid={$q['PostID']}#post{$q['PostID']}",
                    'link'  => sprintf('<a href="artist.php?id=%d">%s</a>', $q['PageID'], display_str($q['ArtistName'])),
                    'title' => 'Artist',
                ];
                break;
            case 'collages':
                $context = [
                    'jump'  => "collages.php?action=comments&amp;collageid={$q['PageID']}&amp;postid={$q['PostID']}#post{$q['PostID']}",
                    'link'  => sprintf('<a href="collages.php?id=%d">%s</a>', $q['PageID'], display_str($q['CollageName'])),
                    'title' => 'Collage',
                ];
                break;
            case 'forums':
                $post = $postMan->findById($q['PostID']);
                $context = [
                    'jump'  => $post->url(),
                    'link'  => $post->thread()->forum()->link() . ' &rsaquo; ' . $post->thread()->link() . ' &rsaquo; ' . $post->link(),
                    'title' => 'Forums',
                ];
                break;
            case 'requests':
                $request = $reqMan->findById($q['PageID']);
                if (is_null($request)) {
                    continue 2;
                }
                $context = [
                    'jump'  => $request->url() . "&amp;postid={$q['PostID']}#post{$q['PostID']}",
                    'link'  => $request->smartLink(),
                    'title' => 'Request',
                ];
                break;
            case 'torrents':
                $tgroup = $tgMan->findById($q['PageID']);
                if (is_null($tgroup)) {
                    continue 2;
                }
                $context = [
                    'jump' => "torrents.php?id={$q['PageID']}&amp;postid={$q['PostID']}#post{$q['PostID']}",
                    'link' => $tgroup->link(),
                    'title' => 'Torrent',
                ];
                break;
            }
            $page[] = array_merge(
                $context,
                [
                    'date'      => $q['Date'],
                    'page'      => $q['Page'],
                    'quoter_id' => $q['QuoterID'],
                    'unread'    => (bool)$q['UnRead'],
                ]
            );
        }
        return $page;
    }

    /**
     * Returns whether or not the current user has new quote notifications.
     * @return int Number of unread quote notifications
     */
    public function unreadTotal(): int {
        $key = sprintf(self::UNREAD_QUOTE_KEY, $this->user->id());
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $forMan = new \Gazelle\Manager\Forum();
            [$cond, $args] = $forMan->configureForUser(new \Gazelle\User($this->user->id()));
            $args[] = $this->user->id(); // for q.UserID
            $total = (int)self::$db->scalar("
                SELECT count(*)
                FROM users_notify_quoted AS q
                LEFT JOIN forums_topics AS t ON (t.ID = q.PageID)
                LEFT JOIN forums AS f ON (f.ID = t.ForumID)
                LEFT JOIN collages AS c ON (q.Page = 'collages' AND c.ID = q.PageID)
                WHERE q.UnRead = true
                    AND (q.Page != 'forums' OR " . implode(' AND ', $cond) . ")
                    AND (q.Page != 'collages' OR c.Deleted = '0')
                    AND q.UserID = ?
                ", ...$args
            );
            self::$cache->cache_value($key, $total, 0);
        }
        return $total;
    }
}
