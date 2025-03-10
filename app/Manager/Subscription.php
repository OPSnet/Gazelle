<?php

namespace Gazelle\Manager;

class Subscription extends \Gazelle\Base {
    /**
     * For all subscribers of a forum thread or artist/collage/request/torrent comments, clear
     *   - subscription cache
     *   - quote notification
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     * @param int $PageID, ID of the above
     * @return int total number of cache expiries
     */
    public function flushPage(string $Page, int $PageID): int {
        $qid = self::$db->get_query_id();
        if ($Page === 'forums') {
            self::$db->prepared_query('
                SELECT UserID FROM users_subscriptions WHERE TopicID = ?
                ', $PageID
            );
        } else {
            self::$db->prepared_query('
                SELECT UserID FROM users_subscriptions_comments WHERE Page = ?  AND PageID = ?
                ', $Page, $PageID
            );
        }

        $list = self::$db->collect('UserID', false);
        $affected = count($list);
        self::$cache->delete_multi(array_map(fn($id) => "subscriptions_user_new_$id", $list));

        self::$db->prepared_query('
            SELECT UserID FROM users_notify_quoted WHERE Page = ?  AND PageID = ?
            ', $Page, $PageID
        );
        $list = self::$db->collect('UserID', false);

        foreach ($list as $userId) {
            (new \Gazelle\User\Quote(new \Gazelle\User($userId)))->flush();
        }

        self::$db->set_query_id($qid);
        return $affected + count($list);
    }

    /**
     * Move all $Page subscriptions from $OldPageID to $NewPageID (for example when merging torrent groups).
     * @param string $Page 'forums', 'artist', 'collages', 'requests' or 'torrents'
     */
    public function move(string $Page, int $OldPageID, ?int $NewPageID): int {
        if ($Page == 'forums') {
            if ($NewPageID !== null) {
                self::$db->prepared_query('
                    UPDATE IGNORE users_subscriptions SET
                        TopicID = ?
                    WHERE TopicID = ?
                    ', $NewPageID, $OldPageID
                );
                // explanation see below
                self::$db->prepared_query('
                    UPDATE IGNORE forums_last_read_topics SET
                        TopicID = ?
                    WHERE TopicID = ?
                    ', $NewPageID, $OldPageID
                );
                self::$db->prepared_query('
                    SELECT UserID, min(PostID)
                    FROM forums_last_read_topics
                    WHERE TopicID IN (?, ?)
                    GROUP BY UserID
                    HAVING COUNT(1) = 2
                    ', $NewPageID, $OldPageID
                );
                $Results = self::$db->to_array(false, MYSQLI_NUM, false);
                foreach ($Results as $Result) {
                    self::$db->prepared_query('
                        UPDATE forums_last_read_topics SET
                            PostID = ?
                        WHERE TopicID = ?
                            AND UserID = ?
                        ', $Result[1], $NewPageID, $Result[0]
                    );
                }
            }
            self::$db->prepared_query('
                DELETE FROM users_subscriptions WHERE TopicID = ?
                ', $OldPageID
            );
            self::$db->prepared_query("
                DELETE FROM forums_last_read_topics WHERE TopicID = ?
                ", $OldPageID
            );
        } else {
            if ($NewPageID !== null) {
                self::$db->prepared_query('
                    UPDATE IGNORE users_subscriptions_comments SET
                        PageID = ?
                    WHERE Page = ?
                        AND PageID = ?
                    ', $NewPageID, $Page, $OldPageID
                );
                // last read handling
                // 1) update all rows that have no key collisions (i.e. users that haven't previously read both pages or if there are only comments on one page)
                self::$db->prepared_query('
                    UPDATE IGNORE users_comments_last_read SET
                        PageID = ?
                    WHERE Page = ?
                        AND PageID = ?
                    ', $NewPageID, $Page, $OldPageID
                );
                // 2) get all last read records with key collisions (i.e. there are records for one user for both PageIDs)
                self::$db->prepared_query('
                    SELECT UserID, min(PostID)
                    FROM users_comments_last_read
                    WHERE Page = ?
                        AND PageID IN (?, ?)
                    GROUP BY UserID
                    HAVING count(1) = 2
                    ', $Page, $OldPageID, $NewPageID
                );
                $Results = self::$db->to_array(false, MYSQLI_NUM, false);
                // 3) update rows for those people found in 2) to the earlier post
                foreach ($Results as $Result) {
                    self::$db->prepared_query('
                        UPDATE users_comments_last_read SET
                            PostID = ?
                        WHERE Page = ?
                            AND PageID = ?
                            AND UserID = ?
                        ', $Result[1], $Page, $NewPageID, $Result[0]
                    );
                }
            }
            self::$db->prepared_query('
                DELETE FROM users_subscriptions_comments
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $OldPageID
            );
            self::$db->prepared_query('
                DELETE FROM users_comments_last_read
                WHERE Page = ?
                    AND PageID = ?
                ', $Page, $OldPageID
            );
        }
        return $this->flushPage($Page, $OldPageID);
    }
}
