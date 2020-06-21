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
        $Matches = [];
        $Pattern = sprintf('/\[quote(?:=(%s)(?:\|.*)?)?]|\[\/quote]|@(%s)/i', USERNAME_REGEX_SHORT, USERNAME_REGEX_SHORT);
        preg_match_all($Pattern, $Body, $Matches, PREG_SET_ORDER);

        if (count($Matches)) {
            $Usernames = [];
            $Level = 0;
            foreach ($Matches as $M) {
                if ($M[0] != '[/quote]') {
                    // @mentions
                    if ($Level == 0 && isset($M[2])) {
                        $Usernames[] = $M[2];
                        continue;
                    } else if ($Level == 0 && isset($M[1])) {
                        $Usernames[] = preg_replace('/(^[.,]*)|([.,]*$)/', '', $M[1]); // wut?
                    }
                    ++$Level;
                } else {
                    --$Level;
                }
            }
        }

        if (!count($Usernames)) {
            return;
        }

        // remove any dupes in the array (the fast way)
        // TODO: replace the above with $Usernames[$M[2]] = 1
        //       (once the $Usernames[] = preg_replace() construct is understood)
        //       and the following statement can be removed
        $Usernames = array_flip(array_flip($Usernames));

        $this->db->prepared_query("
            SELECT m.ID
            FROM users_main AS m
            LEFT JOIN users_info AS i ON (i.UserID = m.ID)
            WHERE i.NotifyOnQuote = '1'
                AND i.UserID != ?
                AND m.Username IN (" . placeholders($Usernames) . ")
            ", $this->userId, ...$Usernames
        );

        $Results = $this->db->to_array();
        $notification = new Notification;
        $info = \Users::user_info($this->UserID);
        foreach ($Results as $Result) {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_notify_quoted
                    (UserID, QuoterID, Page, PageID, PostID)
                VALUES
                    (?,      ?,        ?,    ?,      ?)
                ', $Result['ID'], $this->userId, $Page, $PageID, $PostID
            );
            $this->cache->delete_value("notify_quoted_" . $Result['ID']);
            $URL = site_url() . (
                ($Page == 'forums')
                    ? "forums.php?action=viewthread&postid=$PostID"
                    : "comments.php?action=jump&postid=$PostID"
            );
            $notification->push($Result['ID'], 'New Quote!', 'Quoted by ' . $info['Username'] . " $URL", $URL, Notification::QUOTES);
        }
        $this->db->set_query_id($QueryID);
    }

    /**
     * (Un)subscribe from a forum thread.
     * @param int $TopicID
     */
    public function subscribe($TopicID) {
        $QueryID = $this->db->get_query_id();
        $UserSubscriptions = $this->subscriptions();
        $Key = $this->isSubscribed($TopicID);
        if ($Key !== false) {
            $this->db->prepared_query('
                DELETE FROM users_subscriptions
                WHERE UserID = ?
                    AND TopicID = ?
                ', $this->userId, $TopicID
            );
            unset($UserSubscriptions[$Key]);
        } else {
            $this->db->prepared_query('
                INSERT IGNORE INTO users_subscriptions (UserID, TopicID)
                VALUES (?, ?)
                ', $this->userId, $TopicID
            );
            array_push($UserSubscriptions, $TopicID);
        }
        $this->cache->replace_value("subscriptions_user_" . $this->userId, $UserSubscriptions, 0);
        $this->cache->delete_value("subscriptions_user_new_" . $this->userId);
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
        $QueryID = $this->db->get_query_id();

        $NewSubscriptions = $this->cache->get_value('subscriptions_user_new_' . $this->userId);
        if ($NewSubscriptions === false) {
            // forum subscriptions
            // TODO: refactor this shit and all the other places user_forums_sql is called.
            $this->db->query("
                SELECT COUNT(1)
                FROM users_subscriptions AS s
                LEFT JOIN forums_last_read_topics AS l ON (l.UserID = s.UserID AND l.TopicID = s.TopicID)
                INNER JOIN forums_topics AS t ON (t.ID = s.TopicID)
                INNER JOIN forums AS f ON (f.ID = t.ForumID)
                WHERE " . \Forums::user_forums_sql() . "
                    AND IF(t.IsLocked = '1' AND t.IsSticky = '0'" . ", t.LastPostID, IF(l.PostID IS NULL, 0, l.PostID)) < t.LastPostID
                    AND s.UserID = " . $this->userId);
            list($NewForumSubscriptions) = $this->db->next_record();

            // comment subscriptions
            $this->db->prepared_query("
                SELECT COUNT(1)
                FROM users_subscriptions_comments AS s
                LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = s.UserID AND lr.Page = s.Page AND lr.PageID = s.PageID)
                LEFT JOIN comments AS c ON (c.ID = (SELECT MAX(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
                LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
                WHERE s.UserID = ?
                    AND (s.Page != 'collages' OR co.Deleted = '0')
                    AND IF(lr.PostID IS NULL, 0, lr.PostID) < c.ID
                ", $this->userId
            );
            list($NewCommentSubscriptions) = $this->db->next_record();

            $NewSubscriptions = $NewForumSubscriptions + $NewCommentSubscriptions;
            $this->cache->cache_value('subscriptions_user_new_' . $this->userId, $NewSubscriptions, 0);
        }
        $this->db->set_query_id($QueryID);
        return (int)$NewSubscriptions;
    }

    /**
     * Returns whether or not the current user has new quote notifications.
     * @return int Number of unread quote notifications
     */
    public function unreadQuotes() {
        $QuoteNotificationsCount = $this->cache->get_value('notify_quoted_' . $this->userId);
        if ($QuoteNotificationsCount === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->query("
                SELECT COUNT(1)
                FROM users_notify_quoted AS q
                LEFT JOIN forums_topics AS t ON (t.ID = q.PageID)
                LEFT JOIN forums AS f ON (f.ID = t.ForumID)
                LEFT JOIN collages AS c ON (q.Page = 'collages' AND c.ID = q.PageID)
                WHERE q.UserID = " . $this->userId . "
                    AND q.UnRead
                    AND (q.Page != 'forums' OR " . \Forums::user_forums_sql() . ")
                    AND (q.Page != 'collages' OR c.Deleted = '0')");
            list($QuoteNotificationsCount) = $this->db->next_record();
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value('notify_quoted_' . $this->userId, $QuoteNotificationsCount, 0);
        }
        return (int)$QuoteNotificationsCount;
    }

    /**
     * Returns the key which holds this $TopicID in the subscription array.
     * Use type-aware comparison operators with this! (ie. if (isSubscribed($TopicID) !== false) { ... })
     * @param int $TopicID
     * @return bool|int
     */
    public function isSubscribed($TopicID) {
        return array_search($TopicID, $this->subscriptions());
    }

    /**
     * Same as has_subscribed, but for comment subscriptions.
     * @param string $Page 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID
     * @return bool|int
     */
    public function isSubscribedComments($Page, $PageID) {
        return array_search([$Page, $PageID], $this->commentSubscriptions());
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
            $this->cache->delete_value("notify_quoted_$Subscriber");
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
}
