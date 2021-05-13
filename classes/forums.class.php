<?php
class Forums {
    /**
     * Get all forums that the current user has special access to ("Extra forums" in the profile)
     * @return array Array of ForumIDs
     */
    public static function get_permitted_forums() {
        global $LoggedUser;
        return isset($LoggedUser['CustomForums']) ? array_keys($LoggedUser['CustomForums'], 1) : [];
    }

    /**
     * Get all forums that the current user does not have access to ("Restricted forums" in the profile)
     * @return array Array of ForumIDs
     */
    public static function get_restricted_forums() {
        global $LoggedUser;
        return isset($LoggedUser['CustomForums']) ? array_keys($LoggedUser['CustomForums'], 0) : [];
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
        global $LoggedUser;
        if ($donorMan->hasForumAccess(new Gazelle\User($LoggedUser['ID'])) && !in_array(DONOR_FORUM, $PermittedForums)) {
            $PermittedForums[] = DONOR_FORUM;
        }
        $SQL = "((f.MinClassRead <= '" . $LoggedUser['Class'] . "'";
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
