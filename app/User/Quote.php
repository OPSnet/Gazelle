<?php

namespace Gazelle\User;

class Quote extends \Gazelle\Base {

    protected $user;
    protected $showAll = false;

    public function __construct(\Gazelle\User $user) {
        parent::__construct();
        $this->user = $user;
    }

    /**
     * Toggle whether only unread quotes should be listed
     *
     * @param bool $showAll false if only unread should be shown, true to show everything
     */
    public function setShowAll(bool $showAll) {
        $this->showAll = $showAll;
        return $this;
    }

    /**
     * Are only unread quotes displayed?
     *
     * @return bool true if all quotes are displayed
     */
    public function showAll(): bool {
        return $this->showAll;
    }

    /**
     * Mark all unread quotes as having been seen by a user
     *
     * @return int Number of quotes cleared
     */
    public function clear(): int {
        $this->db->prepared_query("
            UPDATE users_notify_quoted SET
                UnRead = '0'
            WHERE UserID = ?
            ", $this->user->id()
        );
        $this->cache->delete_value('user_quote_unread_' . $this->user->id());
        return $this->db->affected_rows();
    }

    /**
     * Mark the user as having seen their quoted posts in a thread
     *
     * @param int $threadId The ID of the thread
     * @param int $firstPost The first post in the thread
     * @param int $lastPost The most recent post in the thread
     */
    public function clearThread(int $threadId, int $firstPost, int $lastPost): bool {
        $this->db->prepared_query("
            UPDATE users_notify_quoted SET
                UnRead = false
            WHERE Page = 'forums'
                AND UserID = ?
                AND PageID = ?
                AND PostID BETWEEN ? AND ?
            ", $this->user->id(), $threadId, $firstPost, $lastPost
        );
        $this->cache->delete_value('user_quote_unread_' . $this->user->id());
        return $this->db->affected_rows() === 1;
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
    protected function configure() {
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
     *
     * @return int total quotes
     */
    public function total() {
        [$cond, $args] = $this->configure();
        return $this->db->scalar("
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
    public function page(int $limit, int $offset) {
        [$cond, $args] = $this->configure();
        $args = array_merge($args, [$limit, $offset]);
        $this->db->prepared_query("
            SELECT q.Page,
                q.PageID,
                q.PostID,
                q.QuoterID,
                q.Date,
                q.UnRead,
                f.ID as ForumID,
                f.Name as ForumName,
                t.Title as ForumTitle,
                a.Name as ArtistName,
                c.Name as CollageName
            FROM users_notify_quoted AS q
            LEFT JOIN forums_topics  AS t ON (t.ID = q.PageID)
            LEFT JOIN forums         AS f ON (f.ID = t.ForumID)
            LEFT JOIN artists_group  AS a ON (a.ArtistID = q.PageID)
            LEFT JOIN collages       AS c ON (c.ID = q.PageID)
            WHERE " . join(' AND ', $cond) . "
            ORDER BY q.Date DESC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        $quoteList = $this->db->to_array(false, MYSQLI_ASSOC, false);
        $requestList = \Requests::get_requests(
            array_column(array_filter($quoteList, function ($x) { return $x['Page'] === 'requests'; }), 'PageID'),
            true
        );
        $torrentList = \Torrents::get_groups(
            array_column(array_filter($quoteList, function ($x) { return $x['Page'] === 'torrents'; }), 'PageID'),
            true, true, false
        );

        $page = [];
        $releaseType = new \Gazelle\ReleaseType;
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
                $context = [
                    'jump' => "forums.php?action=viewthread&amp;threadid={$q['PageID']}&amp;postid={$q['PostID']}#post{$q['PostID']}",
                    'link' => sprintf('<a href="forums.php?action=viewforum&amp;forumid=%d" class="tooltip" title="%s">%s</a>',
                        $q['ForumID'], display_str($q['ForumTitle']), shortenString($q['ForumTitle'], 75)
                    ),
                    'title' => 'Forums',
                ];
                break;
            case 'requests':
                if (!isset($requestList[$q['PageID']])) {
                    continue 2;
                }
                $request = $requestList[$q['PageID']];
                switch (CATEGORY[$request['CategoryID'] - 1]) {
                    case 'Music':
                        $link = \Artists::display_artists(\Requests::get_artists($q['PageID']))
                            . sprintf('<a href="requests.php?action=view&amp;id=%d" dir="ltr">%s [%d]</a>',
                                $q['PageID'], $request['Title'], $request['Year']);
                        break;
                    case 'Audiobooks':
                    case 'Comedy':
                        $link = sprintf('<a href="requests.php?action=view&amp;id=%d" dir="ltr">%s [%d]</a>',
                                $q['PageID'], $request['Title'], $request['Year']);
                        break;
                    default:
                        $link = sprintf('<a href="requests.php?action=view&amp;id=%d" dir="ltr">%s</a>',
                                $q['PageID'], $request['Title']);
                        break;
                }
                $context = [
                    'jump'  => "requests.php?action=view&amp;id={$q['PageID']}&amp;postid={$q['PostID']}#post{$q['PostID']}",
                    'link'  => $link,
                    'title' => 'Request',
                ];
                break;
            case 'torrents':
                if (!isset($torrentList[$q['PageID']])) {
                    continue 2;
                }
                $group = $torrentList[$q['PageID']];
                $context = [
                    'jump' => "torrents.php?id={$q['PageID']}&amp;postid={$q['PostID']}#post{$q['PostID']}",
                    'link' => \Artists::display_artists($group['ExtendedArtists'])
                        . sprintf('<a href="torrents.php?id=%d" dir="ltr">%s [%d] [%s]</a>',
                            $q['PageID'], $group['Name'], $group['Year'], $releaseType->findNameById($group['ReleaseType'])),
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
}
