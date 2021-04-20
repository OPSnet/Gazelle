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
        if ($donorMan->hasForumAccess(new Gazelle\User(G::$LoggedUser['ID'])) && !in_array(DONOR_FORUM, $PermittedForums)) {
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
}
