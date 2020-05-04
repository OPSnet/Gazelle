<?php

use \Gazelle\Manager\Notification;

class Subscriptions {
    /**
     * Parse a post/comment body for quotes and notify all quoted users that have quote notifications enabled.
     * @param string $Body
     * @param int $PostID
     * @param string $Page
     * @param int $PageID
     */
    public static function quote_notify($Body, $PostID, $Page, $PageID) {
        $QueryID = G::$DB->get_query_id();
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
        $Usernames = array_flip(array_flip($Usernames));

        $Placeholders = implode(', ', array_fill(0, count($Usernames), '?'));
        G::$DB->prepared_query("
            SELECT m.ID
            FROM users_main AS m
            LEFT JOIN users_info AS i ON (i.UserID = m.ID)
            WHERE i.NotifyOnQuote = '1'
                AND i.UserID != ?
                AND m.Username IN ($Placeholders)
            ", G::$LoggedUser['ID'], ...$Usernames
        );

        $Results = G::$DB->to_array();
        foreach ($Results as $Result) {
            $UserID = $Result['ID'];
            $QuoterID = G::$LoggedUser['ID'];
            $Page = $Page;
            $PageID = $PageID;
            $PostID = $PostID;

            G::$DB->prepared_query('
                INSERT IGNORE INTO users_notify_quoted
                    (UserID, QuoterID, Page, PageID, PostID, Date)
                VALUES
                    (?,      ?,        ?,    ?,      ?,      now())
                ', $UserID, $QuoterID, $Page, $PageID, $PostID
            );
            G::$Cache->delete_value("notify_quoted_$UserID");
            if ($Page == 'forums') {
                $URL = site_url() . "forums.php?action=viewthread&postid=$PostID";
            } else {
                $URL = site_url() . "comments.php?action=jump&postid=$PostID";
            }
            Notification::send_push($UserID, 'New Quote!', 'Quoted by ' . G::$LoggedUser['Username'] . " $URL", $URL, Notification::QUOTES);
        }
        G::$DB->set_query_id($QueryID);
    }

    /**
     * (Un)subscribe from a forum thread.
     * If UserID == 0, G::$LoggedUser[ID] is used
     * @param int $TopicID
     * @param int $UserID
     */
    public static function subscribe($TopicID, $UserID = 0) {
        if ($UserID == 0) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserSubscriptions = self::get_subscriptions();
        $Key = self::has_subscribed($TopicID);
        if ($Key !== false) {
            G::$DB->prepared_query('
                DELETE FROM users_subscriptions
                WHERE UserID = ?
                    AND TopicID = ?
                ', $UserID, $TopicID
            );
            unset($UserSubscriptions[$Key]);
        } else {
            G::$DB->prepared_query('
                INSERT IGNORE INTO users_subscriptions (UserID, TopicID)
                VALUES (?, ?)
                ', $UserID, $TopicID
            );
            array_push($UserSubscriptions, $TopicID);
        }
        G::$Cache->replace_value("subscriptions_user_$UserID", $UserSubscriptions, 0);
        G::$Cache->delete_value("subscriptions_user_new_$UserID");
        G::$DB->set_query_id($QueryID);
    }

    /**
     * (Un)subscribe from comments.
     * If UserID == 0, G::$LoggedUser[ID] is used
     * @param string $Page 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID ArtistID, CollageID, RequestID or GroupID
     * @param int $UserID
     */
    public static function subscribe_comments($Page, $PageID, $UserID = 0) {
        if ($UserID == 0) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserCommentSubscriptions = self::get_comment_subscriptions();
        $Key = self::has_subscribed_comments($Page, $PageID);
        if ($Key !== false) {
            G::$DB->prepared_query('
                DELETE FROM users_subscriptions_comments
                WHERE UserID = ?
                    AND Page = ?
                    AND PageID = ?
                ', $UserID, $Page, $PageID
            );
            unset($UserCommentSubscriptions[$Key]);
        } else {
            G::$DB->prepared_query('
                INSERT IGNORE INTO users_subscriptions_comments
                    (UserID, Page, PageID)
                VALUES
                    (?,      ?,    ?)
                ', $UserID, $Page, $PageID
            );
            array_push($UserCommentSubscriptions, [$Page, $PageID]);
        }
        G::$Cache->replace_value("subscriptions_comments_user_$UserID", $UserCommentSubscriptions, 0);
        G::$Cache->delete_value("subscriptions_comments_user_new_$UserID");
        G::$DB->set_query_id($QueryID);
    }

    /**
     * Read $UserID's subscriptions. If the cache key isn't set, it gets filled.
     * If UserID == 0, G::$LoggedUser[ID] is used
     * @param int $UserID
     * @return array Array of TopicIDs
     */
    public static function get_subscriptions($UserID = 0) {
        if ($UserID == 0) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserSubscriptions = G::$Cache->get_value("subscriptions_user_$UserID");
        if ($UserSubscriptions === false) {
            G::$DB->prepared_query('
                SELECT TopicID
                FROM users_subscriptions
                WHERE UserID = ?
                ', $UserID
            );
            $UserSubscriptions = G::$DB->collect(0);
            G::$Cache->cache_value("subscriptions_user_$UserID", $UserSubscriptions, 0);
        }
        G::$DB->set_query_id($QueryID);
        return $UserSubscriptions;
    }

    /**
     * Same as self::get_subscriptions, but for comment subscriptions
     * @param int $UserID
     * @return array Array of ($Page, $PageID)
     */
    public static function get_comment_subscriptions($UserID = 0) {
        if ($UserID == 0) {
            $UserID = G::$LoggedUser['ID'];
        }
        $QueryID = G::$DB->get_query_id();
        $UserCommentSubscriptions = G::$Cache->get_value("subscriptions_comments_user_$UserID");
        if ($UserCommentSubscriptions === false) {
            G::$DB->prepared_query('
                SELECT Page, PageID
                FROM users_subscriptions_comments
                WHERE UserID = ?
                ', $UserID
            );
            $UserCommentSubscriptions = G::$DB->to_array(false, MYSQLI_NUM);
            G::$Cache->cache_value("subscriptions_comments_user_$UserID", $UserCommentSubscriptions, 0);
        }
        G::$DB->set_query_id($QueryID);
        return $UserCommentSubscriptions;
    }

    /**
     * Returns whether or not the current user has new subscriptions. This handles both forum and comment subscriptions.
     * @return int Number of unread subscribed threads/comments
     */
    public static function has_new_subscriptions() {
        $QueryID = G::$DB->get_query_id();

        $NewSubscriptions = G::$Cache->get_value('subscriptions_user_new_' . G::$LoggedUser['ID']);
        if ($NewSubscriptions === false) {
            // forum subscriptions
            // TODO: refactor this shit and all the other places user_forums_sql is called.
            G::$DB->query("
                SELECT COUNT(1)
                FROM users_subscriptions AS s
                LEFT JOIN forums_last_read_topics AS l ON (l.UserID = s.UserID AND l.TopicID = s.TopicID)
                INNER JOIN forums_topics AS t ON (t.ID = s.TopicID)
                INNER JOIN forums AS f ON (f.ID = t.ForumID)
                WHERE " . Forums::user_forums_sql() . "
                    AND IF(t.IsLocked = '1' AND t.IsSticky = '0'" . ", t.LastPostID, IF(l.PostID IS NULL, 0, l.PostID)) < t.LastPostID
                    AND s.UserID = " . G::$LoggedUser['ID']);
            list($NewForumSubscriptions) = G::$DB->next_record();

            // comment subscriptions
            G::$DB->prepared_query("
                SELECT COUNT(1)
                FROM users_subscriptions_comments AS s
                LEFT JOIN users_comments_last_read AS lr ON (lr.UserID = s.UserID AND lr.Page = s.Page AND lr.PageID = s.PageID)
                LEFT JOIN comments AS c ON (c.ID = (SELECT MAX(ID) FROM comments WHERE Page = s.Page AND PageID = s.PageID))
                LEFT JOIN collages AS co ON (s.Page = 'collages' AND co.ID = s.PageID)
                WHERE s.UserID = ?
                    AND (s.Page != 'collages' OR co.Deleted = '0')
                    AND IF(lr.PostID IS NULL, 0, lr.PostID) < c.ID
                ", G::$LoggedUser['ID']
            );
            list($NewCommentSubscriptions) = G::$DB->next_record();

            $NewSubscriptions = $NewForumSubscriptions + $NewCommentSubscriptions;
            G::$Cache->cache_value('subscriptions_user_new_' . G::$LoggedUser['ID'], $NewSubscriptions, 0);
        }
        G::$DB->set_query_id($QueryID);
        return (int)$NewSubscriptions;
    }

    /**
     * Returns whether or not the current user has new quote notifications.
     * @return int Number of unread quote notifications
     */
    public static function has_new_quote_notifications() {
        $QuoteNotificationsCount = G::$Cache->get_value('notify_quoted_' . G::$LoggedUser['ID']);
        if ($QuoteNotificationsCount === false) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
                SELECT COUNT(1)
                FROM users_notify_quoted AS q
                    LEFT JOIN forums_topics AS t ON t.ID = q.PageID
                    LEFT JOIN forums AS f ON f.ID = t.ForumID
                    LEFT JOIN collages AS c ON q.Page = 'collages' AND c.ID = q.PageID
                WHERE q.UserID = " . G::$LoggedUser['ID'] . "
                    AND q.UnRead
                    AND (q.Page != 'forums' OR " . Forums::user_forums_sql() . ")
                    AND (q.Page != 'collages' OR c.Deleted = '0')");
            list($QuoteNotificationsCount) = G::$DB->next_record();
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value('notify_quoted_' . G::$LoggedUser['ID'], $QuoteNotificationsCount, 0);
        }
        return (int)$QuoteNotificationsCount;
    }

    /**
     * Returns the key which holds this $TopicID in the subscription array.
     * Use type-aware comparison operators with this! (ie. if (self::has_subscribed($TopicID) !== false) { ... })
     * @param int $TopicID
     * @return bool|int
     */
    public static function has_subscribed($TopicID) {
        $UserSubscriptions = self::get_subscriptions();
        return array_search($TopicID, $UserSubscriptions);
    }

    /**
     * Same as has_subscribed, but for comment subscriptions.
     * @param string $Page 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID
     * @return bool|int
     */
    public static function has_subscribed_comments($Page, $PageID) {
        $UserCommentSubscriptions = self::get_comment_subscriptions();
        return array_search([$Page, $PageID], $UserCommentSubscriptions);
    }

    /**
     * Clear the subscription cache for all subscribers of a forum thread or artist/collage/request/torrent comments.
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param type $PageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     */
    public static function flush_subscriptions($Page, $PageID) {
        $QueryID = G::$DB->get_query_id();
        if ($Page == 'forums') {
            G::$DB->prepared_query('
                SELECT UserID
                FROM users_subscriptions
                WHERE TopicID = ?
                ', $PageID
            );
        } else {
            G::$DB->prepared_query('
                SELECT UserID
                FROM users_subscriptions_comments
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $PageID
            );
        }
        $Subscribers = G::$DB->collect('UserID');
        foreach ($Subscribers as $Subscriber) {
            G::$Cache->delete_value("subscriptions_user_new_$Subscriber");
        }
        G::$DB->set_query_id($QueryID);
    }

    /**
     * Move all $Page subscriptions from $OldPageID to $NewPageID (for example when merging torrent groups).
     * Passing $NewPageID = null will delete the subscriptions.
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param int $OldPageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     * @param int|null $NewPageID As $OldPageID, or null to delete the subscriptions
     */
    public static function move_subscriptions($Page, $OldPageID, $NewPageID) {
        self::flush_subscriptions($Page, $OldPageID);
        $QueryID = G::$DB->get_query_id();
        if ($Page == 'forums') {
            if ($NewPageID !== null) {
                G::$DB->prepared_query('
                    UPDATE IGNORE users_subscriptions
                    SET TopicID = ?
                    WHERE TopicID = ?
                    ', $NewPageID, $OldPageID
                );
                // explanation see below
                G::$DB->prepared_query('
                    UPDATE IGNORE forums_last_read_topics
                    SET TopicID = ?
                    WHERE TopicID = ?
                    ', $NewPageID, $OldPageID
                );
                G::$DB->prepared_query('
                    SELECT UserID, min(PostID)
                    FROM forums_last_read_topics
                    WHERE TopicID IN (?, ?)
                    GROUP BY UserID
                    HAVING COUNT(1) = 2
                    ', $NewPageID, $OldPageID
                );
                $Results = G::$DB->to_array(false, MYSQLI_NUM);
                foreach ($Results as $Result) {
                    G::$DB->prepared_query('
                        UPDATE forums_last_read_topics
                        SET PostID = ?
                        WHERE TopicID = ?
                            AND UserID = ?
                        ', $Result[1], $NewPageID, $Result[0]
                    );
                }
            }
            G::$DB->prepared_query('
                DELETE FROM users_subscriptions
                WHERE TopicID = ?
                ', $OldPageID
            );
            G::$DB->prepared_query("
                DELETE FROM forums_last_read_topics
                WHERE TopicID = ?
                ", $OldPageID
            );
        } else {
            if ($NewPageID !== null) {
                G::$DB->prepared_query('
                    UPDATE IGNORE users_subscriptions_comments
                    SET PageID = ?
                    WHERE Page = ?
                        AND PageID = ?
                    ', $NewPageID, $Page, $OldPageID
                );
                // last read handling
                // 1) update all rows that have no key collisions (i.e. users that haven't previously read both pages or if there are only comments on one page)
                G::$DB->prepared_query('
                    UPDATE IGNORE users_comments_last_read
                    SET PageID = ?
                    WHERE Page = ?
                        AND PageID = ?
                    ', $NewPageID, $Page, $OldPageID
                );
                // 2) get all last read records with key collisions (i.e. there are records for one user for both PageIDs)
                G::$DB->prepared_query('
                    SELECT UserID, min(PostID)
                    FROM users_comments_last_read
                    WHERE Page = ?
                        AND PageID IN (?, ?)
                    GROUP BY UserID
                    HAVING count(1) = 2
                    ', $Page, $OldPageID, $NewPageID
                );
                $Results = G::$DB->to_array(false, MYSQLI_NUM);
                // 3) update rows for those people found in 2) to the earlier post
                foreach ($Results as $Result) {
                    G::$DB->prepared_query('
                        UPDATE users_comments_last_read
                        SET PostID = ?
                        WHERE Page = ?
                            AND PageID = ?
                            AND UserID = ?
                        ', $Result[1], $Page, $NewPageID, $Result[0]
                    );
                }
            }
            G::$DB->prepared_query('
                DELETE FROM users_subscriptions_comments
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $OldPageID
            );
            G::$DB->prepared_query('
                DELETE FROM users_comments_last_read
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $OldPageID
            );
        }
        G::$DB->set_query_id($QueryID);
    }

    /**
     * Clear the quote notification cache for all subscribers of a forum thread or artist/collage/request/torrent comments.
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID TopicID, ArtistID, CollageID, RequestID or GroupID, respectively
     */
    public static function flush_quote_notifications($Page, $PageID) {
        $QueryID = G::$DB->get_query_id();
        G::$DB->prepared_query('
            SELECT UserID
            FROM users_notify_quoted
            WHERE Page = ?
                AND PageID = ?
            ', $Page, $PageID
        );
        $Subscribers = G::$DB->collect('UserID');
        foreach ($Subscribers as $Subscriber) {
            G::$Cache->delete_value("notify_quoted_$Subscriber");
        }
        G::$DB->set_query_id($QueryID);
    }
}
