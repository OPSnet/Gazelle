<?php

namespace Gazelle\User;

class Subscription extends \Gazelle\BaseUser {
    protected const CACHE_KEY = "subscriptions_user_%d";
    protected const NEW_KEY = "subscriptions_user_new_%d";
    protected array $threadList;

    public function flush(): Subscription {
        $this->threadList = [];
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->user->id()),
            sprintf(self::NEW_KEY, $this->user->id()),
        ]);
        return $this;
    }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }
    public function tableName(): string { return 'users_subscriptions'; }

    /**
     * (Un)subscribe from a forum thread.
     */
    public function subscribe(int $threadId): int {
        if ($this->isSubscribed($threadId) !== false) {
            self::$db->prepared_query('
                DELETE FROM users_subscriptions
                WHERE UserID = ?
                    AND TopicID = ?
                ', $this->user->id(), $threadId
            );
            $affected = self::$db->affected_rows();
        } else {
            self::$db->prepared_query('
                INSERT IGNORE INTO users_subscriptions (UserID, TopicID)
                VALUES (?, ?)
                ', $this->user->id(), $threadId
            );
            $affected = self::$db->affected_rows();
        }
        $this->flush();
        return $affected;
    }

    /**
     * (Un)subscribe from comments.
     * @param string $page 'artist', 'collages', 'requests' or 'torrents'
     * @param int $pageID ArtistID, CollageID, RequestID or GroupID
     */
    public function subscribeComments(string $page, int $pageID): bool {
        $qid = self::$db->get_query_id();
        $subscriptions = $this->commentSubscriptions();
        $key = -1;
        foreach ($subscriptions as $k => $entry) {
            if ($entry[0] === $page && $entry[1] === $pageID) {
                $key = $k;
                break;
            }
        }
        $success = false;
        if ($key !== -1) {
            self::$db->prepared_query('
                DELETE FROM users_subscriptions_comments
                WHERE UserID = ?
                    AND Page = ?
                    AND PageID = ?
                ', $this->user->id(), $page, $pageID
            );
            $success = self::$db->affected_rows() == 1;
            unset($subscriptions[$key]);
        } else {
            self::$db->prepared_query('
                INSERT IGNORE INTO users_subscriptions_comments
                    (UserID, Page, PageID)
                VALUES
                    (?,      ?,    ?)
                ', $this->user->id(), $page, $pageID
            );
            $success = true;
            array_push($subscriptions, [$page, $pageID]);
        }
        self::$cache->cache_value("subscriptions_comments_user_" . $this->user->id(), $subscriptions, 0);
        self::$db->set_query_id($qid);
        return $success;
    }

    /**
     * Read users's subscriptions. If the cache key isn't set, it gets filled.
     * @return array Array of TopicIDs
     */
    public function subscriptionList(): array {
        if (isset($this->threadList) && !empty($this->threadList)) {
            return $this->threadList;
        }
        $list = self::$cache->get_value("subscriptions_user_" . $this->user->id());
        if ($list === false) {
            self::$db->prepared_query('
                SELECT TopicID FROM users_subscriptions WHERE UserID = ?
                ', $this->user->id()
            );
            $list = self::$db->collect(0, false);
            self::$cache->cache_value("subscriptions_user_" . $this->user->id(), $list, 0);
        }
        $this->threadList = $list;
        return $this->threadList;
    }

    /**
     * Same as subscriptions(), but for comment subscriptions
     * @return array Array of ($Page, $PageID)
     */
    public function commentSubscriptions(): array {
        $qid = self::$db->get_query_id();
        $list = self::$cache->get_value("subscriptions_comments_user_" . $this->user->id());
        if ($list === false) {
            self::$db->prepared_query('
                SELECT Page, PageID
                FROM users_subscriptions_comments
                WHERE UserID = ?
                ', $this->user->id()
            );
            $list = self::$db->to_array(false, MYSQLI_NUM, false);
            self::$cache->cache_value("subscriptions_comments_user_" . $this->user->id(), $list, 0);
        }
        self::$db->set_query_id($qid);
        return $list;
    }

    /**
     * Returns whether or not a user has new subscriptions. This handles both forum and comment subscriptions.
     * @return int Number of unread subscribed threads/comments
     */
    public function unread(): int {
        $unread = self::$cache->get_value('subscriptions_user_new_' . $this->user->id());
        if ($unread === false) {
            $unread = (new \Gazelle\Manager\Forum)->unreadSubscribedForumTotal($this->user) + $this->unreadCommentTotal();
            self::$cache->cache_value('subscriptions_user_new_' . $this->user->id(), $unread, 0);
        }
        return $unread;
    }

    /**
     * How many subscribed entities (artists, collages, requests, torrents)
     * have new comments on them?
     */
    public function unreadCommentTotal(): int {
        return (int)self::$db->scalar("
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
        return (int)self::$db->scalar("
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

    public function isSubscribed(int $threadId): bool {
        return array_search($threadId, $this->subscriptionList()) !== false;
    }

    /**
     * Same as has_subscribed, but for comment subscriptions.
     * @param string $page 'artist', 'collages', 'requests' or 'torrents'
     */
    public function isSubscribedComments(string $page, int $pageId): bool {
        return !empty(array_filter($this->commentSubscriptions(),
            fn($s) => $s[0] === $page && $s[1] == $pageId)
        );
    }

    /**
     * Clear the forum subscription notifications of a user.
     */
    public function clear(): int {
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics (UserID, TopicID, PostID)
                SELECT us.UserID, ft.ID, ft.LastPostID
                FROM forums_topics ft
                INNER JOIN users_subscriptions us ON (us.TopicID = ft.ID)
                WHERE us.UserID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $this->user->id()
        );
        self::$cache->delete_value('subscriptions_user_new_' . $this->user->id());
        return self::$db->affected_rows();
    }

    /**
     * Mark all subscribed forums and comments of the user as read
     *
     * @return int number of forums+comments that were caught up
     */
    public function catchupSubscriptions(): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO forums_last_read_topics (UserID, TopicID, PostID)
                SELECT us.UserID, ft.ID, ft.LastPostID
                FROM users_subscriptions us
                INNER JOIN forums_topics ft ON (ft.ID = us.TopicID)
                WHERE us.UserID = ?
            ON DUPLICATE KEY UPDATE PostID = LastPostID
            ", $this->user->id()
        );
        $n = self::$db->affected_rows();
        self::$db->prepared_query("
            INSERT INTO users_comments_last_read (UserID, Page, PageID, PostID)
                SELECT s.UserID, s.Page, s.PageID, IFNULL(c.ID, 0)
                FROM users_subscriptions_comments AS s
                LEFT JOIN comments AS c ON (c.Page = s.Page AND c.ID =
                    (SELECT max(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
                WHERE s.UserID = ?
            ON DUPLICATE KEY UPDATE PostID = IFNULL(c.ID, 0)
            ", $this->user->id()
        );
        $n += self::$db->affected_rows();
        self::$db->commit();
        self::$cache->delete_value('subscriptions_user_new_' . $this->user->id());
        return $n;
    }

    public function latestSubscriptionList(bool $showUnread, int $limit, int $offset): array {
        $forMan = new \Gazelle\Manager\Forum;
        [$cond, $args] = $forMan->configureForUser($this->user);
        if ($showUnread) {
            $cond[] = "p.ID > if(t.IsLocked = '1' AND t.IsSticky = '0', p.ID, coalesce(lr.PostID, 0))";
        }
        $userId = $this->user->id();
        $cond[] = "s.UserID = ?";
        array_push($args, $userId, $limit, $offset);

        self::$db->prepared_query("
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
                um.avatar AS LastReadAvatar,
                c_lr.EditedUserID AS LastReadEditedUserID
            FROM users_subscriptions_comments AS s
            LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = ? AND lr.Page = s.Page AND lr.PageID = s.PageID)
            LEFT JOIN artists_group AS a ON (s.Page = 'artist' AND a.ArtistID = s.PageID)
            LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
            LEFT JOIN comments AS c ON
                (c.ID = (SELECT max(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
            LEFT JOIN comments AS c_lr ON (c_lr.ID = lr.PostID)
            LEFT JOIN users_main AS um ON (um.ID = c_lr.AuthorID)
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
                um.avatar,
                p_lr.EditedUserID
            FROM users_subscriptions AS s
            LEFT JOIN forums_last_read_topics AS lr ON (lr.UserID = ? AND s.TopicID = lr.TopicID)
            LEFT JOIN forums_topics AS t ON (t.ID = s.TopicID)
            LEFT JOIN forums AS f ON (f.ID = t.ForumID)
            LEFT JOIN forums_posts AS p ON
                (p.ID = (SELECT max(ID) FROM forums_posts WHERE TopicID = s.TopicID))
            LEFT JOIN forums_posts AS p_lr ON (p_lr.ID = lr.PostID)
            LEFT JOIN users_main AS um ON (um.ID = p_lr.AuthorID)
            WHERE " . implode(' AND ', $cond) . "
            GROUP BY t.ID
        ORDER BY LastPostTime DESC
        LIMIT ? OFFSET ?
            ", $userId, $userId, $userId, ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
