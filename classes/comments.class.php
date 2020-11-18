<?php
class Comments {
    /*
     * For all functions:
     * $Page = 'artist', 'collages', 'requests' or 'torrents'
     * $PageID = ArtistID, CollageID, RequestID or GroupID, respectively
     */

    /**
     * Post a comment on an artist, request or torrent page.
     * @param string $Page
     * @param int $PageID
     * @param string $Body
     * @return int ID of the new comment
     */
    public static function post($Page, $PageID, $Body) {
        $QueryID = G::$DB->get_query_id();
        G::$DB->prepared_query("
            INSERT INTO comments
                   (Page, PageID, AuthorID, Body)
            VALUES (?,    ?,      ?,        ?)
            ", $Page, $PageID, G::$LoggedUser['ID'], $Body
        );
        $PostID = G::$DB->inserted_id();
        $Pages = G::$DB->scalar("
            SELECT ceil(count(*) / ?)
            FROM comments
            WHERE Page = ? AND PageID = ?
            ", TORRENT_COMMENTS_PER_PAGE, $Page, $PageID
        );

        $CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE * $Pages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        G::$Cache->delete_value($Page.'_comments_'.$PageID.'_catalogue_'.$CatalogueID);
        G::$Cache->delete_value($Page.'_comments_'.$PageID);
        if ($Page == 'collages') {
            G::$Cache->delete_value($Page.'_comments_recent_'.$PageID);
        }

        $subscription = new \Gazelle\Manager\Subscription(G::$LoggedUser['ID']);
        $subscription->flush($Page, $PageID);
        $subscription->quoteNotify($Body, $PostID, $Page, $PageID);

        G::$DB->set_query_id($QueryID);

        return $PostID;
    }

    /**
     * Edit a comment
     * @param int $PostID
     * @param string $NewBody
     * @param bool $SendPM If true, send a PM to the author of the comment informing him about the edit
     * @todo move permission check out of here/remove hardcoded error(404)
     */
    public static function edit($PostID, $NewBody, $SendPM = false) {
        $QueryID = G::$DB->get_query_id();

        [$OldBody, $AuthorID, $Page, $PageID, $AddedTime] = G::$DB->row("
            SELECT
                Body,
                AuthorID,
                Page,
                PageID,
                AddedTime
            FROM comments
            WHERE ID = ?
            ", $PostID
        );
        if (!$PageID) {
            return false;
        }
        if (G::$LoggedUser['ID'] != $AuthorID && !check_perms('site_moderate_forums')) {
            return false;
        }

        // Perform the update
        G::$DB->prepared_query("
            UPDATE comments SET
                EditedTime = now(),
                Body = ?,
                EditedUserID = ?
            WHERE ID = ?
            ", trim($NewBody), G::$LoggedUser['ID'], $PostID
        );
        $success = G::$DB->affected_rows() === 1;

        G::$DB->prepared_query("
            INSERT INTO comments_edits
                   (Page, PostID, EditUser, Body, EditTime)
            VALUES (?,    ?,      ?,        ?,    now())
            ", $Page, $PostID, G::$LoggedUser['ID'], $OldBody
        );

        $CommPage = G::$DB->scalar("
            SELECT ceil(count(*) / ?) AS Page
            FROM comments
            WHERE Page = ?
                AND PageID = ?
                AND ID <= ?
            ", TORRENT_COMMENTS_PER_PAGE, $Page, $PageID, $PostID
        );
        G::$DB->set_query_id($QueryID);

        // Update the cache
        $CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE * $CommPage - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        G::$Cache->delete_value($Page . '_comments_' . $PageID . '_catalogue_' . $CatalogueID);

        if ($Page == 'collages') {
            // On collages, we also need to clear the collage key (collage_$CollageID), because it has the comments in it... (why??)
            G::$Cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $PageID));
        }

        if ($SendPM && G::$LoggedUser['ID'] != $AuthorID) {
            // Send a PM to the user to notify them of the edit
            $PMurl = SITE_URL."/comments.php?action=jump&postid=$PostID";
            $ProfLink = '[url='.SITE_URL.'/user.php?id='.G::$LoggedUser['ID'].']'.G::$LoggedUser['Username'].'[/url]';
            $PMBody = "One of your comments has been edited by $ProfLink: [url]{$PMurl}[/url]";
            Misc::send_pm($AuthorID, 0, "Your comment #$PostID has been edited", $PMBody);
        }

        return $success;
    }

    /**
     * Delete a comment
     * @param int $PostID
     */
    public static function delete($PostID) {
        $QueryID = G::$DB->get_query_id();
        [$Page, $PageID] = G::$DB->scalar("
            SELECT Page, PageID FROM comments WHERE ID = ?
            ", $PostID
        );
        if (!$PageID) {
            // no such comment?
            G::$DB->set_query_id($QueryID);
            return false;
        }
        [$CommPages, $CommPage] = G::$DB->scalar("
            SELECT
                ceil(count(*) / ?) AS Pages,
                ceil(sum(if(ID <= ?, 1, 0)) / ?) AS Page
            FROM comments
            WHERE Page = ? AND PageID = ?
            GROUP BY PageID
            ", TORRENT_COMMENTS_PER_PAGE, $PostID, TORRENT_COMMENTS_PER_PAGE, $Page, $PageID
        );
        if (!$CommPages) {
            // the comment $PostID was probably not posted on $Page
            G::$DB->set_query_id($QueryID);
            return false;
        }

        // $CommPages = number of pages in the thread
        // $CommPage = which page the post is on
        // These are set for cache clearing.

        G::$DB->prepared_query("
            DELETE FROM comments WHERE ID = ?
            ", $PostID
        );
        G::$DB->prepared_query("
            DELETE FROM comments_edits WHERE Page = ? AND PostID = ?
            ", $Page, $PostID
        );
        G::$DB->prepared_query("
            DELETE FROM users_notify_quoted WHERE Page = ? AND PostID = ?
            ", $Page, $PostID
        );

        $subscription = new \Gazelle\Manager\Subscription;
        $subscription->flush($Page, $PageID);
        $subscription->flushQuotes($Page, $PageID);

        //We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
        $ThisCatalogue = floor((TORRENT_COMMENTS_PER_PAGE * $CommPage - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        $LastCatalogue = floor((TORRENT_COMMENTS_PER_PAGE * $CommPages - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);
        for ($i = $ThisCatalogue; $i <= $LastCatalogue; ++$i) {
            G::$Cache->delete_value($Page . '_comments_' . $PageID . '_catalogue_' . $i);
        }

        G::$Cache->delete_value($Page . '_comments_' . $PageID);

        if ($Page == 'collages') {
            // On collages, we also need to clear the collage key (collage_$CollageID), because it has the comments in it... (why??)
            G::$Cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $PageID));
        }

        G::$DB->set_query_id($QueryID);

        return true;
    }

    /**
     * Get the URL to a comment, already knowing the Page and PostID
     * @param string $Page
     * @param int $PageID
     * @param int $PostID
     * @return string|bool The URL to the comment or false on error
     */
    public static function get_url($Page, $PageID, $PostID = null) {
        $Post = (!empty($PostID) ? "&postid=$PostID#post$PostID" : '');
        switch ($Page) {
            case 'artist':
                return "artist.php?id=$PageID$Post";
            case 'collages':
                return "collages.php?action=comments&collageid=$PageID$Post";
            case 'requests':
                return "requests.php?action=view&id=$PageID$Post";
            case 'torrents':
                return "torrents.php?id=$PageID$Post";
            default:
                return false;
        }
    }

    /**
     * Get the URL to a comment
     * @param int $PostID
     * @return string|bool The URL to the comment or false on error
     */
    public static function get_url_query($PostID) {
        [$Page, $PageID] = G::$DB->row("
            SELECT Page, PageID FROM comments WHERE ID = ?
            ", $PostID
        );
        if (!PageID) {
            error(404);
        }
        return self::get_url($Page, $PageID, $PostID);
    }

    /**
     * Load a page's comments. This takes care of `postid` and (indirectly) `page` parameters passed in $_GET.
     * Quote notifications and last read are also handled here, unless $HandleSubscriptions = false is passed.
     * @param string $Page
     * @param int $PageID
     * @param bool $HandleSubscriptions Whether or not to handle subscriptions (last read & quote notifications)
     * @return array ($NumComments, $Page, $Thread, $LastRead)
     *     $NumComments: the total number of comments on this artist/request/torrent group
     *     $Page: the page we're currently on
     *     $Thread: an array of all posts on this page
     *     $LastRead: ID of the last comment read by the current user in this thread;
     *                will be false if $HandleSubscriptions == false or if there are no comments on this page
     */
    public static function load($Page, $PageID, $HandleSubscriptions = true) {
        $QueryID = G::$DB->get_query_id();

        // Get the total number of comments
        $NumComments = G::$Cache->get_value($Page."_comments_$PageID");
        if ($NumComments === false) {
            $NumComments = G::$DB->scalar("
                SELECT count(*) FROM comments WHERE Page = ? AND PageID = ?
                ", $Page, $PageID
            );
            G::$Cache->cache_value($Page."_comments_$PageID", $NumComments, 0);
        }

        // If a postid was passed, we need to determine which page that comment is on.
        // Format::page_limit handles a potential $_GET['page']
        if (isset($_GET['postid']) && is_number($_GET['postid']) && $NumComments > TORRENT_COMMENTS_PER_PAGE) {
            $PostNum = G::$DB->scalar("
                SELECT count(*)
                FROM comments
                WHERE Page = ?
                    AND PageID = ?
                    AND ID <= ?
                ", $Page, $PageID, $_GET['postid']
            );
            list($CommPage, $Limit) = Format::page_limit(TORRENT_COMMENTS_PER_PAGE, $PostNum);
        } else {
            list($CommPage, $Limit) = Format::page_limit(TORRENT_COMMENTS_PER_PAGE, $NumComments);
        }

        // Get the cache catalogue
        $CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE * $CommPage - TORRENT_COMMENTS_PER_PAGE) / THREAD_CATALOGUE);

        // Cache catalogue from which the page is selected
        $Catalogue = G::$Cache->get_value($Page.'_comments_'.$PageID.'_catalogue_'.$CatalogueID);
        if ($Catalogue === false) {
            G::$DB->prepared_query("
                SELECT c.ID,
                    c.AuthorID,
                    c.AddedTime,
                    c.Body,
                    c.EditedUserID,
                    c.EditedTime,
                    u.Username
                FROM comments AS c
                LEFT JOIN users_main AS u ON (u.ID = c.EditedUserID)
                WHERE c.Page = ? AND c.PageID = ?
                ORDER BY c.ID
                LIMIT ? OFFSET ?
                ", $Page, $PageID, $CatalogueID * THREAD_CATALOGUE, THREAD_CATALOGUE
            );
            $Catalogue = G::$DB->to_array(false, MYSQLI_ASSOC);
            G::$Cache->cache_value($Page.'_comments_'.$PageID.'_catalogue_'.$CatalogueID, $Catalogue, 0);
        }

        //This is a hybrid to reduce the catalogue down to the page elements: We use the page limit % catalogue
        $Thread = array_slice($Catalogue, ((TORRENT_COMMENTS_PER_PAGE * $CommPage - TORRENT_COMMENTS_PER_PAGE) % THREAD_CATALOGUE), TORRENT_COMMENTS_PER_PAGE, true);

        if (!($HandleSubscriptions && count($Thread) > 0)) {
            $LastRead = false;
        } else {
            // quote notifications
            $FirstPost = current($Thread);
            $FirstPost = $FirstPost['ID'];
            $LastPost = end($Thread);
            $LastPost = $LastPost['ID'];
            G::$DB->prepared_query("
                UPDATE users_notify_quoted SET
                    UnRead = false
                WHERE UserID = ?
                    AND Page = ?
                    AND PageID = ?
                    AND PostID BETWEEN ? AND ?
                ", G::$LoggedUser['ID'], $Page, $PageID, $FirstPost, $LastPost
            );
            if (G::$DB->affected_rows()) {
                G::$Cache->delete_value('notify_quoted_' . G::$LoggedUser['ID']);
            }

            // last read
            $LastRead = G::$DB->scalar("
                SELECT PostID
                FROM users_comments_last_read
                WHERE UserID = ?
                    AND Page = ?
                    AND PageID = ?
                ", G::$LoggedUser['ID'], $Page, $PageID
            );
            if ($LastRead < $LastPost) {
                G::$DB->prepared_query("
                    INSERT INTO users_comments_last_read
                           (UserID, Page, PageID, PostID)
                    VALUES (?,      ?,    ?,      ?)
                    ON DUPLICATE KEY UPDATE
                        PostID = ?
                    ", G::$LoggedUser['ID'], $Page, $PageID, $LastPost, $LastPost
                );
                G::$Cache->delete_value('subscriptions_user_new_' . G::$LoggedUser['ID']);
            }
        }

        G::$DB->set_query_id($QueryID);
        return [$NumComments, $CommPage, $Thread, $LastRead];
    }

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
            ", TORRENT_COMMENTS_PER_PAGE, $Page, $TargetPageID
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
