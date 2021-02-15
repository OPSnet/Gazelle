<?php
class Comments {
    /*
     * For all functions:
     * $Page = 'artist', 'collages', 'requests' or 'torrents'
     * $PageID = ArtistID, CollageID, RequestID or GroupID, respectively
     */

    /**
     * Load recent collage comments. Used for displaying recent comments on collage pages.
     * @param int $CollageID ID of the collage
     * @return array ($Comments)
     *     $Comments
     *         ID: Comment ID
     *         Body: Comment body
     *         AuthorID: Author of comment
     *         Username: Their username
     *         AddedTime: Date of comment creation
     */
    public static function collageSummary($CollageID, $count = 5) {
        $key = "collages_comments_recent_$CollageID";
        if (($list = G::$Cache->get_value($key)) === false) {
            $qid = G::$DB->get_query_id();
            G::$DB->prepared_query('
                SELECT c.ID AS id,
                    c.Body as body,
                    c.AuthorID as author_id,
                    c.AddedTime as added
                FROM comments AS c
                LEFT JOIN users_main AS um ON (um.ID = c.AuthorID)
                WHERE c.Page = ? AND c.PageID = ?
                ORDER BY c.ID DESC
                LIMIT ?
                ', 'collages', $CollageID, $count
            );
            $list = G::$DB->to_array(false, MYSQLI_ASSOC);
            foreach ($list as &$c) {
                $c['body'] = Text::full_format($c['body']);
            }
            unset($c);
            G::$DB->set_query_id($quid);
            if (count($list)) {
                G::$Cache->cache_value($key, $list, 7200);
            }
        }
        return $list;
    }

    /**
     * Merges all comments from $Page/$PageID into $Page/$TargetPageID. This also takes care of quote notifications, subscriptions and cache.
     * @param type $Page
     * @param type $PageID
     * @param type $TargetPageID
     */
    public static function merge($Page, $PageID, $TargetPageID) {
        $QueryID = G::$DB->get_query_id();

        G::$DB->prepared_query("
            UPDATE comments SET
                PageID = ?
            WHERE Page = ? AND PageID = ?
            ", $TargetPageID, $Page, $PageID
        );

        // quote notifications
        G::$DB->prepared_query("
            UPDATE users_notify_quoted SET
                PageID = ?
            WHERE Page = ? AND PageID = ?
            ", $TargetPageID, $Page, $PageID
        );

        // comment subscriptions
        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->move($Page, $PageID, $TargetPageID);

        // cache (we need to clear all comment catalogues)
        $CommPages = G::$DB->scalar("
            SELECT ceil(count(*) / ?) AS Pages
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $Page, $TargetPageID
        );
        $LastCatalogue = floor((TORRENT_COMMENTS_PER_PAGE * $CommPages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        for ($i = 0; $i <= $LastCatalogue; ++$i) {
            G::$Cache->delete_value($Page . "_comments_$TargetPageID" . "_catalogue_$i");
        }
        G::$Cache->delete_value($Page."_comments_$TargetPageID");
        G::$DB->set_query_id($QueryID);
    }

    /**
     * Delete all comments on $Page/$PageID (deals with quote notifications and subscriptions as well)
     * @param string $Page
     * @param int $PageID
     * @return boolean
     */
    public static function delete_page($Page, $PageID) {
        $QueryID = G::$DB->get_query_id();

        // get number of pages
        $CommPages = G::$DB->scalar("
            SELECT ceil(count(*) / ?) AS Pages
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $Page, $PageID
        );
        if (!$CommPages) {
            return false;
        }

        // Delete comments
        G::$DB->prepared_query("
            DELETE FROM comments WHERE Page = ? AND PageID = ?
            ", $Page, $PageID
        );

        // Delete quote notifications
        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->move($Page, $PageID, null);
        $subscription->flushQuotes($Page, $PageID);

        G::$DB->query("
            DELETE FROM users_notify_quoted WHERE Page = ? AND PageID = ?
            ", $Page, $PageID
        );

        // Clear cache
        $LastCatalogue = floor((TORRENT_COMMENTS_PER_PAGE * $CommPages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        for ($i = 0; $i <= $LastCatalogue; ++$i) {
            G::$Cache->delete_value($Page . '_comments_' . $PageID . '_catalogue_' . $i);
        }
        G::$Cache->delete_value($Page.'_comments_'.$PageID);

        G::$DB->set_query_id($QueryID);

        return true;
    }
}
