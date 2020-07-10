<?php
class Forums {
    const PADLOCK = "\xF0\x9F\x94\x92";
    /**
     * Get information on a thread.
     *
     * @param int $ThreadID
     *            the thread ID.
     * @param boolean $Return
     *            indicates whether thread info should be returned.
     * @param Boolean $SelectiveCache
     *            cache thread info/
     * @return array holding thread information.
     */
    public static function get_thread_info($ThreadID, $Return = true, $SelectiveCache = false) {
        if ((!$ThreadInfo = G::$Cache->get_value('thread_' . $ThreadID . '_info')) || !isset($ThreadInfo['Ranking'])) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
                SELECT
                    t.Title,
                    t.ForumID,
                    t.IsLocked,
                    t.IsSticky,
                    COUNT(fp.id) AS Posts,
                    t.LastPostAuthorID,
                    ISNULL(p.TopicID) AS NoPoll,
                    t.StickyPostID,
                    t.AuthorID as OP,
                    t.Ranking,
                    MAX(fp.AddedTime) as LastPostTime
                FROM forums_topics AS t
                    JOIN forums_posts AS fp ON fp.TopicID = t.ID
                    LEFT JOIN forums_polls AS p ON p.TopicID = t.ID
                WHERE t.ID = '$ThreadID'
                GROUP BY fp.TopicID");
            if (!G::$DB->has_results()) {
                G::$DB->set_query_id($QueryID);
                return null;
            }
            $ThreadInfo = G::$DB->next_record(MYSQLI_ASSOC, false);
            if ($ThreadInfo['StickyPostID']) {
                $ThreadInfo['Posts']--;
                G::$DB->query(
                    "SELECT
                        p.ID,
                        p.AuthorID,
                        p.AddedTime,
                        p.Body,
                        p.EditedUserID,
                        p.EditedTime,
                        ed.Username
                        FROM forums_posts AS p
                            LEFT JOIN users_main AS ed ON ed.ID = p.EditedUserID
                        WHERE p.TopicID = '$ThreadID'
                            AND p.ID = '" . $ThreadInfo['StickyPostID'] . "'");
                list ($ThreadInfo['StickyPost']) = G::$DB->to_array(false, MYSQLI_ASSOC);
            }
            G::$DB->set_query_id($QueryID);
            if (!$SelectiveCache || !$ThreadInfo['IsLocked'] || $ThreadInfo['IsSticky']) {
                G::$Cache->cache_value('thread_' . $ThreadID . '_info', $ThreadInfo, 0);
            }
        }
        if ($Return) {
            return $ThreadInfo;
        }
    }

    /**
     * Checks whether user has permissions on a forum.
     *
     * @param int $ForumID
     *            the forum ID.
     * @param string $Perm
     *            the permissision to check, defaults to 'Read'
     * @return boolean true if user has permission
     */
    public static function check_forumperm($ForumID, $Perm = 'Read') {
        $Forums = self::get_forums();
        if (isset(G::$LoggedUser['CustomForums'][$ForumID]) && G::$LoggedUser['CustomForums'][$ForumID] == 1) {
            return true;
        }
        $donorMan = new Gazelle\Manager\Donation;
        if ($ForumID == DONOR_FORUM && $donorMan->hasForumAccess(G::$LoggedUser['ID'])) {
            return true;
        }
        if ($Forums[$ForumID]['MinClass' . $Perm] > G::$LoggedUser['Class'] && (!isset(G::$LoggedUser['CustomForums'][$ForumID]) || G::$LoggedUser['CustomForums'][$ForumID] == 0)) {
            return false;
        }
        if (isset(G::$LoggedUser['CustomForums'][$ForumID]) && G::$LoggedUser['CustomForums'][$ForumID] == 0) {
            return false;
        }
        return true;
    }

    /**
     * Gets basic info on a forum.
     *
     * @param int $ForumID
     *            the forum ID.
     */
    public static function get_forum_info($ForumID) {
        $Forum = G::$Cache->get_value("ForumInfo_$ForumID");
        if (!$Forum) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
                SELECT
                    Name,
                    MinClassRead,
                    MinClassWrite,
                    MinClassCreate,
                    COUNT(forums_topics.ID) AS Topics
                FROM forums
                    LEFT JOIN forums_topics ON forums_topics.ForumID = forums.ID
                WHERE forums.ID = '$ForumID'
                GROUP BY ForumID");
            if (!G::$DB->has_results()) {
                return false;
            }
            // Makes an array, with $Forum['Name'], etc.
            $Forum = G::$DB->next_record(MYSQLI_ASSOC);

            G::$DB->set_query_id($QueryID);

            G::$Cache->cache_value("ForumInfo_$ForumID", $Forum, 86400);
        }
        return $Forum;
    }

    /**
     * Get the forum categories
     * @return array ForumCategoryID => Name
     */
    public static function get_forum_categories() {
        $ForumCats = G::$Cache->get_value('forums_categories');
        if ($ForumCats === false) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
                SELECT ID, Name
                FROM forums_categories
                ORDER BY Sort, Name");
            $ForumCats = [];
            while (list ($ID, $Name) = G::$DB->next_record()) {
                $ForumCats[$ID] = $Name;
            }
            G::$DB->set_query_id($QueryID);
            G::$Cache->cache_value('forums_categories', $ForumCats, 0);
        }
        return $ForumCats;
    }

    /**
     * Get the forums
     * @return array ForumID => (various information about the forum)
     */
    public static function get_forums() {
        if (!$Forums = G::$Cache->get_value('forums_list')) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->prepared_query("
                SELECT
                    f.ID,
                    f.CategoryID,
                    f.Name,
                    f.Description,
                    f.MinClassRead AS MinClassRead,
                    f.MinClassWrite AS MinClassWrite,
                    f.MinClassCreate AS MinClassCreate,
                    f.NumTopics,
                    f.NumPosts,
                    f.LastPostID,
                    f.LastPostAuthorID,
                    f.LastPostTopicID,
                    f.LastPostTime,
                    t.Title,
                    t.IsLocked AS Locked,
                    t.IsSticky AS Sticky
                FROM forums AS f
                INNER JOIN forums_categories AS fc ON (fc.ID = f.CategoryID)
                LEFT JOIN forums_topics AS t ON (t.ID = f.LastPostTopicID)
                GROUP BY f.ID
                ORDER BY fc.Sort, fc.Name, f.CategoryID, f.Sort, f.Name
            ");
            $Forums = G::$DB->to_array('ID', MYSQLI_ASSOC, false);
            G::$Cache->cache_value('forums_list', $Forums, 0);
        }
        return $Forums;
    }

    /**
     * Get all forums that the current user has special access to ("Extra forums" in the profile)
     * @return array Array of ForumIDs
     */
    public static function get_permitted_forums() {
        return isset(G::$LoggedUser['CustomForums']) ? array_keys(G::$LoggedUser['CustomForums'], 1) : [];
    }

    /**
     * Get all forums that the current user does not have access to ("Restricted forums" in the profile)
     * @return array Array of ForumIDs
     */
    public static function get_restricted_forums() {
        return isset(G::$LoggedUser['CustomForums']) ? array_keys(G::$LoggedUser['CustomForums'], 0) : [];
    }

    /**
     * Get the last read posts for the current user
     * @param array $Forums Array of forums as returned by self::get_forums()
     * @return array TopicID => array(TopicID, PostID, Page) where PostID is the ID of the last read post and Page is the page on which that post is
     */
    public static function get_last_read($Forums) {
        if (isset(G::$LoggedUser['PostsPerPage'])) {
            $PerPage = G::$LoggedUser['PostsPerPage'];
        } else {
            $PerPage = POSTS_PER_PAGE;
        }
        $TopicIDs = [];
        foreach ($Forums as $Forum) {
            if (!empty($Forum['LastPostTopicID'])) {
                $TopicIDs[] = $Forum['LastPostTopicID'];
            }
        }
        if (!empty($TopicIDs)) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
                SELECT
                    l.TopicID,
                    l.PostID,
                    CEIL(
                        (
                            SELECT
                                COUNT(p.ID)
                            FROM forums_posts AS p
                            WHERE p.TopicID = l.TopicID
                                AND p.ID <= l.PostID
                        ) / $PerPage
                    ) AS Page
                FROM forums_last_read_topics AS l
                WHERE l.TopicID IN(" . implode(',', $TopicIDs) . ") AND
                    l.UserID = '" . G::$LoggedUser['ID'] . "'");
            $LastRead = G::$DB->to_array('TopicID', MYSQLI_ASSOC);
            G::$DB->set_query_id($QueryID);
        } else {
            $LastRead = [];
        }
        return $LastRead;
    }

    /**
     * Create the part of WHERE in the sql queries used to filter forums for a
     * specific user (MinClassRead, restricted and permitted forums).
     * @return string
     */
    public static function user_forums_sql() {
        // I couldn't come up with a good name, please rename this if you can. -- Y
        $RestrictedForums = self::get_restricted_forums();
        $PermittedForums = self::get_permitted_forums();
        $donorMan = new Gazelle\Manager\Donation;
        if ($donorMan->hasForumAccess(G::$LoggedUser['ID']) && !in_array(DONOR_FORUM, $PermittedForums)) {
            $PermittedForums[] = DONOR_FORUM;
        }
        $SQL = "((f.MinClassRead <= '" . G::$LoggedUser['Class'] . "'";
        if (count($RestrictedForums)) {
            $SQL .= " AND f.ID NOT IN ('" . implode("', '", $RestrictedForums) . "')";
        }
        $SQL .= ')';
        if (count($PermittedForums)) {
            $SQL .= " OR f.ID IN ('" . implode("', '", $PermittedForums) . "')";
        }
        $SQL .= ')';
        return $SQL;
    }

    public static function get_transitions($user = 0) {
        if (!$user) {
            $user = G::$LoggedUser['ID'];
        }
        $info = Users::user_info($user);

        if ($user != G::$LoggedUser['ID'] && !check_perms('users_mod', $info['Class'])) {
            error(403);
        }

        $items = G::$Cache->get_value('forum_transitions');
        if (!$items) {
            $queryId = G::$DB->get_query_id();
            G::$DB->prepared_query('
                SELECT forums_transitions_id AS id, source, destination, label, permission_levels,
                       permission_class, permissions, user_ids
                FROM forums_transitions');
            $items = G::$DB->to_array('id', MYSQLI_ASSOC);
            G::$Cache->cache_value('forum_transitions', $items);
            G::$DB->set_query_id($queryId);
        }

        if ($user == G::$LoggedUser['ID'] && Permissions::has_override(G::$LoggedUser['EffectiveClass'])) {
            return $items;
        }

        $heavyInfo = Users::user_heavy_info($user);
        $info = array_merge($info, $heavyInfo);
        $info['Permissions'] = Permissions::get_permissions_for_user($user, $info['CustomPermissions']);

        $info['ExtraClassesOff'] = array_flip(array_map(function ($i) { return -$i; }, array_keys($info['ExtraClasses'])));
        $info['PermissionsOff'] = array_flip(array_map(function ($i) { return "-$i"; }, array_keys($info['Permissions'])));

        return array_filter($items, function ($item) use ($info, $user) {
            $userClass = $item['permission_class'];
            $secondaryClasses = array_fill_keys(explode(',', $item['permission_levels']), 1);
            $permissions = array_fill_keys(explode(',', $item['permissions']), 1);
            $users = array_fill_keys(explode(',', $item['user_ids']), 1);

            if (count(array_intersect_key($secondaryClasses, $info['ExtraClassesOff'])) > 0) {
                return false;
            }

            if (count(array_intersect_key($permissions, $info['PermissionsOff'])) > 0) {
                return false;
            }

            if (count(array_intersect_key($users, [-$user => 1])) > 0) {
                return false;
            }

            if (count(array_intersect_key($secondaryClasses, $info['ExtraClasses'])) > 0) {
                return true;
            }

            if (count(array_intersect_key($permissions, $info['Permissions'])) > 0) {
                return true;
            }

            if (count(array_intersect_key($users, [$user => 1])) > 0) {
                return true;
            }

            if ($info['EffectiveClass'] >= $userClass) {
                return true;
            }

            return false;
        });
    }

    public static function get_thread_transitions($forum) {
        $transitions = self::get_transitions();

        $filtered = [];
        foreach ($transitions as $transition) {
            if ($transition['source'] == $forum) {
                $filtered[] = $transition;
            }
        }

        return $filtered;
    }

    public static function bbcodeForumUrl($val) {
        $cacheKey = 'bbcode-forum.' . $val;
        list($id, $name) = G::$Cache->get_value($cacheKey);
        if (is_null($id)) {
            list($id, $name) = (int)$val > 0
                ? G::$DB->row('SELECT ID, Name FROM forums WHERE ID = ?', $val)
                : G::$DB->row('SELECT ID, Name FROM forums WHERE Name = ?', $val);
            G::$Cache->cache_value($cacheKey, [$id, $name], 86400 + rand(1, 3600));
        }
        if (!self::check_forumperm($id)) {
            $name = 'restricted';
        }
        return $name
            ? sprintf('<a href="forums.php?action=viewforum&forumid=%d">%s</a>', $id, $name)
            : '[forum]' . $val . '[/forum]';
    }

    public static function bbcodeThreadUrl($thread, $post = null) {
        if (strpos($thread, ':') !== false) {
            list($thread, $post) = explode(':', $thread);
        }

        $cacheKey = 'bbcode-thread.' . $thread;
        list($id, $name, $isLocked, $forumId) = G::$Cache->get_value($cacheKey);
        if (is_null($forumId)) {
            list($id, $name, $isLocked, $forumId) = G::$DB->row('SELECT ID, Title, IsLocked, ForumID FROM forums_topics WHERE ID = ?', $thread);
            G::$Cache->cache_value($cacheKey, [$id, $name, $isLocked, $forumId], 86400 + rand(1, 3600));
        }
        if (!self::check_forumperm($forumId)) {
            $name = 'restricted';
        }

        if ($post) {
            return $id
                ? sprintf('<a href="forums.php?action=viewthread&threadid=%d&postid=%s#post%s">%s%s (Post #%s)</a>',
                    $id, $post, $post, ($isLocked ? self::PADLOCK . ' ' : ''), $name, $post)
                : '[thread]' .  $thread . ':' . $post . '[/thread]';
        }
        return $id
            ? sprintf('<a href="forums.php?action=viewthread&threadid=%d">%s%s</a>',
                $id, ($isLocked ? self::PADLOCK . ' ' : ''), $name)
            : '[thread]' . $thread . '[/thread]';
    }
}
