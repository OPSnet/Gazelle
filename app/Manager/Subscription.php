<?php

namespace Gazelle\Manager;

class Subscription extends \Gazelle\Base {
    protected $userId;

    public function __construct($userId = null) {
        parent::__construct();
        $this->userId = $userId;
    }

    /**
     * Parse a post/comment body for quotes and notify all quoted users that have quote notifications enabled.
     * @param string $Body
     * @param int $PostID
     * @param string $Page
     * @param int $PageID
     */
    public function quoteNotify($Body, $PostID, $Page, $PageID) {
        $QueryID = $this->db->get_query_id();
        /*
         * Explanation of the parameters PageID and Page: Page contains where
         * this quote comes from and can be forums, artist, collages, requests
         * or torrents. The PageID contains the additional value that is
         * necessary for the users_notify_quoted table. The PageIDs for the
         * different Page are: forums: TopicID artist: ArtistID collages:
         * CollageID requests: RequestID torrents: GroupID
         */

        if (!preg_match_all('/(?:\[quote=|@)' . str_replace('/', '', USERNAME_REGEXP) . '/i', $Body, $match)) {
            return;
        };
        $Usernames = array_unique($match['username']);
        if (empty($Usernames)) {
            return;
        }

        $this->db->prepared_query("
            SELECT m.ID
            FROM users_main AS m
            INNER JOIN users_info AS i ON (i.UserID = m.ID)
            WHERE i.NotifyOnQuote = '1'
                AND i.UserID != ?
                AND m.Username IN (" . placeholders($Usernames) . ")
            ", $this->userId, ...$Usernames
        );

        $Results = $this->db->to_array();
        $notification = new Notification;
        $quotername = (new User)->findById($this->userId)->username();
        foreach ($Results as $Result) {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_notify_quoted
                    (UserID, QuoterID, Page, PageID, PostID)
                VALUES
                    (?,      ?,        ?,    ?,      ?)
                ', $Result['ID'], $this->userId, $Page, $PageID, $PostID
            );
            $this->cache->delete_value("user_quote_unread_" . $Result['ID']);
            $URL = SITE_URL .  ($Page == 'forums')
                ? "/forums.php?action=viewthread&postid=$PostID"
                : "/comments.php?action=jump&postid=$PostID";
            $notification->push($Result['ID'], 'New Quote!',
                "Quoted by $quotername $URL", $URL, Notification::QUOTES);
        }
        $this->db->set_query_id($QueryID);
    }

    /**
     * (Un)subscribe from a forum thread.
     * @param int $TopicID
     */
    public function subscribe(int $TopicID) {
        $QueryID = $this->db->get_query_id();
        if ($this->isSubscribed($TopicID) !== false) {
            $this->db->prepared_query('
                DELETE FROM users_subscriptions
                WHERE UserID = ?
                    AND TopicID = ?
                ', $this->userId, $TopicID
            );
        } else {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_subscriptions (UserID, TopicID)
                VALUES (?, ?)
                ', $this->userId, $TopicID
            );
        }
        $this->cache->deleteMulti(["subscriptions_user_" . $this->userId, "subscriptions_user_new_" . $this->userId]);
        $this->db->set_query_id($QueryID);
    }

    /**
     * (Un)subscribe from comments.
     * @param string $Page 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID ArtistID, CollageID, RequestID or GroupID
     */
    public function subscribeComments($Page, $PageID) {
        $QueryID = $this->db->get_query_id();
        $UserCommentSubscriptions = $this->commentSubscriptions();
        $Key = $this->isSubscribedComments($Page, $PageID);
        if ($Key !== false) {
            $this->db->prepared_query('
                DELETE FROM users_subscriptions_comments
                WHERE UserID = ?
                    AND Page = ?
                    AND PageID = ?
                ', $this->userId, $Page, $PageID
            );
            unset($UserCommentSubscriptions[$Key]);
        } else {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_subscriptions_comments
                    (UserID, Page, PageID)
                VALUES
                    (?,      ?,    ?)
                ', $this->userId, $Page, $PageID
            );
            array_push($UserCommentSubscriptions, [$Page, $PageID]);
        }
        $this->cache->replace_value("subscriptions_comments_user_" . $this->userId, $UserCommentSubscriptions, 0);
        $this->cache->delete_value("subscriptions_comments_user_new_" . $this->userId);
        $this->db->set_query_id($QueryID);
    }

    /**
     * Read users's subscriptions. If the cache key isn't set, it gets filled.
     * @return array Array of TopicIDs
     */
    public function subscriptions() {
        $QueryID = $this->db->get_query_id();
        $UserSubscriptions = $this->cache->get_value("subscriptions_user_" . $this->userId);
        if ($UserSubscriptions === false) {
            $this->db->prepared_query('
                SELECT TopicID
                FROM users_subscriptions
                WHERE UserID = ?
                ', $this->userId
            );
            $UserSubscriptions = $this->db->collect(0);
            $this->cache->cache_value("subscriptions_user_" . $this->userId, $UserSubscriptions, 0);
        }
        $this->db->set_query_id($QueryID);
        return $UserSubscriptions;
    }

    /**
     * Same as subscriptions(), but for comment subscriptions
     * @return array Array of ($Page, $PageID)
     */
    public function commentSubscriptions() {
        $QueryID = $this->db->get_query_id();
        $UserCommentSubscriptions = $this->cache->get_value("subscriptions_comments_user_" . $this->userId);
        if ($UserCommentSubscriptions === false) {
            $this->db->prepared_query('
                SELECT Page, PageID
                FROM users_subscriptions_comments
                WHERE UserID = ?
                ', $this->userId
            );
            $UserCommentSubscriptions = $this->db->to_array(false, MYSQLI_NUM);
            $this->cache->cache_value("subscriptions_comments_user_" . $this->userId, $UserCommentSubscriptions, 0);
        }
        $this->db->set_query_id($QueryID);
        return $UserCommentSubscriptions;
    }

    /**
     * Returns whether or not a user has new subscriptions. This handles both forum and comment subscriptions.
     * @return int Number of unread subscribed threads/comments
     */
    public function unread() {
        $unread = $this->cache->get_value('subscriptions_user_new_' . $this->userId);
        if ($unread === false) {
            $user = new \Gazelle\User($this->userId);
            $unread = (new \Gazelle\Manager\Forum)->unreadSubscribedForumTotal($user)
                + (new \Gazelle\Manager\Comment)->unreadSubscribedCommentTotal($user);
            $this->cache->cache_value('subscriptions_user_new_' . $this->userId, $unread, 0);
        }
        return $unread;
    }

    /**
     * Returns whether or not the current user has new quote notifications.
     * @return int Number of unread quote notifications
     */
    public function unreadQuotes(): int {
        $key = 'user_quote_unread_' . $this->userId;
        $total = $this->cache->get_value($key);
        if ($total === false) {
            $forMan = new \Gazelle\Manager\Forum;
            [$cond, $args] = $forMan->configureForUser(new \Gazelle\User($this->userId));
            $args[] = $this->userId; // for q.UserID
            $total = (int)$this->db->scalar("
                SELECT count(*)
                FROM users_notify_quoted AS q
                LEFT JOIN forums_topics AS t ON (t.ID = q.PageID)
                LEFT JOIN forums AS f ON (f.ID = t.ForumID)
                LEFT JOIN collages AS c ON (q.Page = 'collages' AND c.ID = q.PageID)
                WHERE q.UnRead
                    AND (q.Page != 'forums' OR " . implode(' AND ', $cond). ")
                    AND (q.Page != 'collages' OR c.Deleted = '0')
                    AND q.UserID = ?
                ", ...$args
            );
            $this->cache->cache_value($key, $total, 0);
        }
        return $total;
    }

    /**
     * Returns the key which holds this $TopicID in the subscription array.
     * @param int $TopicID
     * @return bool|int
     */
    public function isSubscribed($TopicID) {
        return array_search($TopicID, $this->subscriptions()) !== false;
    }

    /**
     * Same as has_subscribed, but for comment subscriptions.
     * @param string $Page 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID
     * @return bool|int
     */
    public function isSubscribedComments($page, $pageId) {
        return !empty(array_filter($this->commentSubscriptions(),
            function ($s) use ($page, $pageId) { return $s[0] === $page && $s[1] == $pageId; })
        );
    }

    /**
     * Clear the subscription cache for all subscribers of a forum thread or artist/collage/request/torrent comments.
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param type $PageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     */
    public function flush($Page, $PageID) {
        $QueryID = $this->db->get_query_id();
        if ($Page == 'forums') {
            $this->db->prepared_query('
                SELECT UserID
                FROM users_subscriptions
                WHERE TopicID = ?
                ', $PageID
            );
        } else {
            $this->db->prepared_query('
                SELECT UserID
                FROM users_subscriptions_comments
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $PageID
            );
        }
        $Subscribers = $this->db->collect('UserID');
        foreach ($Subscribers as $Subscriber) {
            $this->cache->delete_value("subscriptions_user_new_$Subscriber");
        }
        $this->db->set_query_id($QueryID);
    }

    /**
     * Move all $Page subscriptions from $OldPageID to $NewPageID (for example when merging torrent groups).
     * Passing $NewPageID = null will delete the subscriptions.
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param int $OldPageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     * @param int|null $NewPageID As $OldPageID, or null to delete the subscriptions
     */
    public function move($Page, $OldPageID, $NewPageID) {
        $this->flush($Page, $OldPageID);
        $QueryID = $this->db->get_query_id();
        if ($Page == 'forums') {
            if ($NewPageID !== null) {
                $this->db->prepared_query('
                    UPDATE IGNORE users_subscriptions
                    SET TopicID = ?
                    WHERE TopicID = ?
                    ', $NewPageID, $OldPageID
                );
                // explanation see below
                $this->db->prepared_query('
                    UPDATE IGNORE forums_last_read_topics
                    SET TopicID = ?
                    WHERE TopicID = ?
                    ', $NewPageID, $OldPageID
                );
                $this->db->prepared_query('
                    SELECT UserID, min(PostID)
                    FROM forums_last_read_topics
                    WHERE TopicID IN (?, ?)
                    GROUP BY UserID
                    HAVING COUNT(1) = 2
                    ', $NewPageID, $OldPageID
                );
                $Results = $this->db->to_array(false, MYSQLI_NUM);
                foreach ($Results as $Result) {
                    $this->db->prepared_query('
                        UPDATE forums_last_read_topics
                        SET PostID = ?
                        WHERE TopicID = ?
                            AND UserID = ?
                        ', $Result[1], $NewPageID, $Result[0]
                    );
                }
            }
            $this->db->prepared_query('
                DELETE FROM users_subscriptions
                WHERE TopicID = ?
                ', $OldPageID
            );
            $this->db->prepared_query("
                DELETE FROM forums_last_read_topics
                WHERE TopicID = ?
                ", $OldPageID
            );
        } else {
            if ($NewPageID !== null) {
                $this->db->prepared_query('
                    UPDATE IGNORE users_subscriptions_comments
                    SET PageID = ?
                    WHERE Page = ?
                        AND PageID = ?
                    ', $NewPageID, $Page, $OldPageID
                );
                // last read handling
                // 1) update all rows that have no key collisions (i.e. users that haven't previously read both pages or if there are only comments on one page)
                $this->db->prepared_query('
                    UPDATE IGNORE users_comments_last_read
                    SET PageID = ?
                    WHERE Page = ?
                        AND PageID = ?
                    ', $NewPageID, $Page, $OldPageID
                );
                // 2) get all last read records with key collisions (i.e. there are records for one user for both PageIDs)
                $this->db->prepared_query('
                    SELECT UserID, min(PostID)
                    FROM users_comments_last_read
                    WHERE Page = ?
                        AND PageID IN (?, ?)
                    GROUP BY UserID
                    HAVING count(1) = 2
                    ', $Page, $OldPageID, $NewPageID
                );
                $Results = $this->db->to_array(false, MYSQLI_NUM);
                // 3) update rows for those people found in 2) to the earlier post
                foreach ($Results as $Result) {
                    $this->db->prepared_query('
                        UPDATE users_comments_last_read
                        SET PostID = ?
                        WHERE Page = ?
                            AND PageID = ?
                            AND UserID = ?
                        ', $Result[1], $Page, $NewPageID, $Result[0]
                    );
                }
            }
            $this->db->prepared_query('
                DELETE FROM users_subscriptions_comments
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $OldPageID
            );
            $this->db->prepared_query('
                DELETE FROM users_comments_last_read
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $OldPageID
            );
        }
        $this->db->set_query_id($QueryID);
    }

    /**
     * Clear the quote notification cache for all subscribers of a forum thread or artist/collage/request/torrent comments.
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     */
    public function flushQuotes($Page, $PageID) {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query('
            SELECT UserID
            FROM users_notify_quoted
            WHERE Page = ?
                AND PageID = ?
            ', $Page, $PageID
        );
        $Subscribers = $this->db->collect('UserID');
        foreach ($Subscribers as $Subscriber) {
            $this->cache->delete_value("user_quote_unread_$Subscriber");
        }
        $this->db->set_query_id($QueryID);
    }

    /**
     * Clear the forum subscription notifications of a user.
     */
    public function clear() {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            INSERT INTO forums_last_read_topics (UserID, TopicID, PostID)
                SELECT us.UserID, ft.ID, ft.LastPostID
                FROM forums_topics ft
                INNER JOIN users_subscriptions us ON (us.TopicID = ft.ID)
                WHERE us.UserID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $this->userId
        );
        $this->db->set_query_id($QueryID);
        $this->cache->delete_value('subscriptions_user_new_' . $this->userId);
    }

    /**
     * Mark all subscribed forums and comments of the user as read
     *
     * @return int number of forums+comments that were caught up
     */
    public function catchupSubscriptions(): int {
        $this->db->begin_transaction();
        $this->db->prepared_query("
            INSERT INTO forums_last_read_topics (UserID, TopicID, PostID)
                SELECT us.UserID, ft.ID, ft.LastPostID
                FROM users_subscriptions us
                INNER JOIN forums_topics ft ON (ft.ID = us.TopicID)
                WHERE us.UserID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $this->userId
        );
        $n = $this->db->affected_rows();
        $this->db->prepared_query("
            INSERT INTO users_comments_last_read (UserID, Page, PageID, PostID)
                SELECT s.UserID, s.Page, s.PageID, IFNULL(c.ID, 0)
                FROM users_subscriptions_comments AS s
                LEFT JOIN comments AS c ON (c.Page = s.Page AND c.ID =
                    (SELECT max(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
                WHERE s.UserID = ?
            ON DUPLICATE KEY UPDATE PostID = IFNULL(c.ID, 0)
            ", $this->userId
        );
        $n += $this->db->affected_rows();
        $this->db->commit();
        $this->cache->delete_value('subscriptions_user_new_' . $this->userId);
        return $n;
    }

    public function latestSubscriptionList(\Gazelle\User $user, bool $showUnread, int $limit, int $offset): array {
        $userId = $user->id();
        $forMan = new \Gazelle\Manager\Forum;
        [$cond, $args] = $forMan->configureForUser($user);
        if ($showUnread) {
            $cond[] = "p.ID > if(t.IsLocked = '1' AND t.IsSticky = '0', p.ID, coalesce(lr.PostID, 0))";
        }
        $cond[] = "s.UserID = ?";
        array_push($args, $userId, $limit, $offset);

        $this->db->prepared_query("
            SELECT s.Page,
                s.PageID,
                lr.PostID,
                null AS ForumID,
                null AS ForumName,
                IF(s.Page = 'artist', a.Name, co.Name) AS Name,
                c.ID AS LastPost,
                c.AddedTime AS LastPostTime,
                c_lr.Body AS LastReadBody,
                c_lr.EditedTime AS LastReadEditedTime,
                um.ID AS LastReadUserID,
                um.Username AS LastReadUsername,
                ui.Avatar AS LastReadAvatar,
                c_lr.EditedUserID AS LastReadEditedUserID
            FROM users_subscriptions_comments AS s
            LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = ? AND lr.Page = s.Page AND lr.PageID = s.PageID)
            LEFT JOIN artists_group AS a ON (s.Page = 'artist' AND a.ArtistID = s.PageID)
            LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
            LEFT JOIN comments AS c ON
                (c.ID = (SELECT max(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
            LEFT JOIN comments AS c_lr ON (c_lr.ID = lr.PostID)
            LEFT JOIN users_main AS um ON (um.ID = c_lr.AuthorID)
            LEFT JOIN users_info AS ui ON (ui.UserID = um.ID)
            WHERE s.Page IN ('artist', 'collages', 'requests', 'torrents')
                AND (s.Page != 'collages' OR co.Deleted = '0')
                " . ($showUnread ? ' AND c.ID > coalesce(lr.PostID, 0)' : '') . "
                AND s.UserID = ?
            GROUP BY s.PageID
        UNION ALL
            SELECT 'forums',
                s.TopicID,
                lr.PostID,
                f.ID,
                f.Name,
                t.Title,
                p.ID,
                p.AddedTime,
                p_lr.Body,
                p_lr.EditedTime,
                um.ID,
                um.Username,
                ui.Avatar,
                p_lr.EditedUserID
            FROM users_subscriptions AS s
            LEFT JOIN forums_last_read_topics AS lr ON (lr.UserID = ? AND s.TopicID = lr.TopicID)
            LEFT JOIN forums_topics AS t ON (t.ID = s.TopicID)
            LEFT JOIN forums AS f ON (f.ID = t.ForumID)
            LEFT JOIN forums_posts AS p ON
                (p.ID = (SELECT max(ID) FROM forums_posts WHERE TopicID = s.TopicID))
            LEFT JOIN forums_posts AS p_lr ON (p_lr.ID = lr.PostID)
            LEFT JOIN users_main AS um ON (um.ID = p_lr.AuthorID)
            LEFT JOIN users_info AS ui ON (ui.UserID = um.ID)
            WHERE " . implode(' AND ', $cond) . "
            GROUP BY t.ID
        ORDER BY LastPostTime DESC
        LIMIT ? OFFSET ?
            ", $userId, $userId, $userId, ...$args
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }
}
