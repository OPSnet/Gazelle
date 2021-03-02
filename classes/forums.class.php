<?php
class Forums {
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
}
