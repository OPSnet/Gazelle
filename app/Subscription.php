<?php

namespace Gazelle;

class Subscription extends BaseUser {

    /**
     * Parse a post/comment body for quotes and notify all quoted users that have quote notifications enabled.
     * @param string $Body
     * @param int $PostID
     * @param string $Page
     * @param int $PageID
     */
    public function quoteNotify(string $Body, int $PostID, string $Page, int $PageID) {
        $qid = $this->db->get_query_id();
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
            ", $this->user->id(), ...$Usernames
        );

        $Results = $this->db->collect(0, false);
        $notification = new Manager\Notification;
        $quotername = $this->user->username();
        foreach ($Results as $userId) {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_notify_quoted
                    (UserID, QuoterID, Page, PageID, PostID)
                VALUES
                    (?,      ?,        ?,    ?,      ?)
                ', $userId, $this->user->id(), $Page, $PageID, $PostID
            );
            $this->cache->delete_value("user_quote_unread_" . $userId);
            $URL = SITE_URL .  ($Page == 'forums')
                ? "/forums.php?action=viewthread&postid=$PostID"
                : "/comments.php?action=jump&postid=$PostID";
            $notification->push($userId, 'New Quote!',
                "Quoted by $quotername $URL", $URL, Manager\Notification::QUOTES);
        }
        $this->db->set_query_id($qid);
    }

    /**
     * (Un)subscribe from a forum thread.
     * @param int $TopicID
     */
    public function subscribe(int $TopicID) {
        $qid = $this->db->get_query_id();
        if ($this->isSubscribed($TopicID) !== false) {
            $this->db->prepared_query('
                DELETE FROM users_subscriptions
                WHERE UserID = ?
                    AND TopicID = ?
                ', $this->user->id(), $TopicID
            );
        } else {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_subscriptions (UserID, TopicID)
                VALUES (?, ?)
                ', $this->user->id(), $TopicID
            );
        }
        $this->cache->deleteMulti(["subscriptions_user_" . $this->user->id(), "subscriptions_user_new_" . $this->user->id()]);
        $this->db->set_query_id($qid);
    }

    /**
     * (Un)subscribe from comments.
     * @param string $Page 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID ArtistID, CollageID, RequestID or GroupID
     */
    public function subscribeComments(string $Page, int $PageID) {
        $qid = $this->db->get_query_id();
        $UserCommentSubscriptions = $this->commentSubscriptions();
        $Key = $this->isSubscribedComments($Page, $PageID);
        if ($Key !== false) {
            $this->db->prepared_query('
                DELETE FROM users_subscriptions_comments
                WHERE UserID = ?
                    AND Page = ?
                    AND PageID = ?
                ', $this->user->id(), $Page, $PageID
            );
            unset($UserCommentSubscriptions[$Key]);
        } else {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_subscriptions_comments
                    (UserID, Page, PageID)
                VALUES
                    (?,      ?,    ?)
                ', $this->user->id(), $Page, $PageID
            );
            array_push($UserCommentSubscriptions, [$Page, $PageID]);
        }
        $this->cache->replace_value("subscriptions_comments_user_" . $this->user->id(), $UserCommentSubscriptions, 0);
        $this->cache->delete_value("subscriptions_comments_user_new_" . $this->user->id());
        $this->db->set_query_id($qid);
    }

    /**
     * Read users's subscriptions. If the cache key isn't set, it gets filled.
     * @return array Array of TopicIDs
     */
    public function subscriptionList(): array {
        $qid = $this->db->get_query_id();
        $UserSubscriptions = $this->cache->get_value("subscriptions_user_" . $this->user->id());
        if ($UserSubscriptions === false) {
            $this->db->prepared_query('
                SELECT TopicID FROM users_subscriptions WHERE UserID = ?
                ', $this->user->id()
            );
            $UserSubscriptions = $this->db->collect(0);
            $this->cache->cache_value("subscriptions_user_" . $this->user->id(), $UserSubscriptions, 0);
        }
        $this->db->set_query_id($qid);
        return $UserSubscriptions;
    }

    /**
     * Same as subscriptions(), but for comment subscriptions
     * @return array Array of ($Page, $PageID)
     */
    public function commentSubscriptions(): array {
        $qid = $this->db->get_query_id();
        $list = $this->cache->get_value("subscriptions_comments_user_" . $this->user->id());
        if ($list === false) {
            $this->db->prepared_query('
                SELECT Page, PageID
                FROM users_subscriptions_comments
                WHERE UserID = ?
                ', $this->user->id()
            );
            $list = $this->db->to_array(false, MYSQLI_NUM);
            $this->cache->cache_value("subscriptions_comments_user_" . $this->user->id(), $list, 0);
        }
        $this->db->set_query_id($qid);
        return $list;
    }

    /**
     * Returns whether or not a user has new subscriptions. This handles both forum and comment subscriptions.
     * @return int Number of unread subscribed threads/comments
     */
    public function unread(): int {
        $unread = $this->cache->get_value('subscriptions_user_new_' . $this->user->id());
        if ($unread === false) {
            $unread = (new Manager\Forum)->unreadSubscribedForumTotal($this->user) + $this->unreadCommentTotal();
            $this->cache->cache_value('subscriptions_user_new_' . $this->user->id(), $unread, 0);
        }
        return $unread;
    }

    /**
     * How many subscribed entities (artists, collages, requests, torrents)
     * have new comments on them?
     */
    public function unreadCommentTotal(): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM users_subscriptions_comments AS s
            LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = s.UserID AND lr.Page = s.Page AND lr.PageID = s.PageID)
            LEFT JOIN comments AS c ON (c.ID = (SELECT MAX(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
            LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
            WHERE (s.Page != 'collages' OR co.Deleted = '0')
                AND coalesce(lr.PostID, 0) < c.ID
                AND s.UserID = ?
            ", $this->user->id()
        );
    }

    /**
     * How many total subscribed entities (artists, collages, requests, torrents)
     */
    public function commentTotal(): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM users_subscriptions_comments AS s
            LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = s.UserID AND lr.Page = s.Page AND lr.PageID = s.PageID)
            LEFT JOIN comments AS c ON (c.ID = (SELECT MAX(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
            LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
            WHERE (s.Page != 'collages' OR co.Deleted = '0')
                AND s.UserID = ?
            ", $this->user->id()
        );
    }

    /**
     * Returns whether or not the current user has new quote notifications.
     * @return int Number of unread quote notifications
     */
    public function unreadQuotes(): int {
        $key = 'user_quote_unread_' . $this->user->id();
        $total = $this->cache->get_value($key);
        if ($total === false) {
            $forMan = new \Gazelle\Manager\Forum;
            [$cond, $args] = $forMan->configureForUser(new \Gazelle\User($this->user->id()));
            $args[] = $this->user->id(); // for q.UserID
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

    public function isSubscribed(int $TopicID): bool {
        return array_search($TopicID, $this->subscriptionList()) !== false;
    }

    /**
     * Same as has_subscribed, but for comment subscriptions.
     * @param string $page 'artist', 'collages', 'requests' or 'torrents'
     */
    public function isSubscribedComments(string $page, int $pageId): bool {
        return !empty(array_filter($this->commentSubscriptions(),
            function ($s) use ($page, $pageId) { return $s[0] === $page && $s[1] == $pageId; })
        );
    }

    /**
     * Clear the forum subscription notifications of a user.
     */
    public function clear() {
        $qid = $this->db->get_query_id();
        $this->db->prepared_query("
            INSERT INTO forums_last_read_topics (UserID, TopicID, PostID)
                SELECT us.UserID, ft.ID, ft.LastPostID
                FROM forums_topics ft
                INNER JOIN users_subscriptions us ON (us.TopicID = ft.ID)
                WHERE us.UserID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $this->user->id()
        );
        $this->db->set_query_id($qid);
        $this->cache->delete_value('subscriptions_user_new_' . $this->user->id());
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
            ", $this->user->id()
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
            ", $this->user->id()
        );
        $n += $this->db->affected_rows();
        $this->db->commit();
        $this->cache->delete_value('subscriptions_user_new_' . $this->user->id());
        return $n;
    }

    public function latestSubscriptionList(bool $showUnread, int $limit, int $offset): array {
        $forMan = new Manager\Forum;
        [$cond, $args] = $forMan->configureForUser($this->user);
        if ($showUnread) {
            $cond[] = "p.ID > if(t.IsLocked = '1' AND t.IsSticky = '0', p.ID, coalesce(lr.PostID, 0))";
        }
        $userId = $this->user->id();
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
