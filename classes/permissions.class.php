<?php
class Permissions {
    public static function list() {
        return \Gazelle\Manager\Privilege::privilegeList();
    }

    /**
     * Check to see if a user has the permission to perform an action
     * This is called by check_perms in util.php, for convenience.
     *
     * @param string $PermissionName
     * @param int $MinClass Return false if the user's class level is below this.
     *
     * @return bool
     */
    public static function check_perms($PermissionName, $MinClass = 0) {
        global $LoggedUser;
        $Override = self::has_override($LoggedUser['EffectiveClass']);
        return ($PermissionName === null ||
            (isset($LoggedUser['Permissions'][$PermissionName]) && $LoggedUser['Permissions'][$PermissionName]))
            && ($LoggedUser['Class'] >= $MinClass
                || $LoggedUser['EffectiveClass'] >= $MinClass
                || $Override);
    }

    /**
     * Gets the permissions associated with a certain permissionid
     *
     * @param int $PermissionID the kind of permissions to fetch
     * @return array permissions
     */
    public static function get_permissions($PermissionID) {
        global $Cache, $DB;
        $Permission = $Cache->get_value("perm_$PermissionID");
        if (empty($Permission)) {
            $QueryID = $DB->get_query_id();
            $DB->prepared_query("
                SELECT Level AS Class, `Values` AS Permissions, Secondary, PermittedForums
                FROM permissions
                WHERE ID = ?
                ", $PermissionID
            );
            $Permission = $DB->next_record(MYSQLI_ASSOC, ['Permissions']);
            $DB->set_query_id($QueryID);
            $Permission['Permissions'] = unserialize($Permission['Permissions']) ?: [];
            $Cache->cache_value("perm_$PermissionID", $Permission, 2592000);
        }
        return $Permission;
    }

    /**
     * Get a user's permissions.
     *
     * @param int $UserID
     * @param array|false $CustomPermissions
     *    Pass in the user's custom permissions if you already have them.
     *    Leave false if you don't have their permissions. The function will fetch them.
     * @return array Mapping of PermissionName=>bool/int
     */
    public static function get_permissions_for_user($UserID, $CustomPermissions = false) {
        $UserInfo = Users::user_info($UserID);

        // Fetch custom permissions if they weren't passed in.
        if ($CustomPermissions === false) {
            global $DB;
            $QueryID = $DB->get_query_id();
            $CustomPermissions = $DB->scalar("
                SELECT CustomPermissions FROM users_main WHERE ID = ?
                ", $UserID
            );
            $DB->set_query_id($QueryID);
        }

        if (!empty($CustomPermissions) && !is_array($CustomPermissions)) {
            $CustomPermissions = unserialize($CustomPermissions);
        }

        $Permissions = self::get_permissions($UserInfo['PermissionID']);

        // Manage 'special' inherited permissions
        $BonusPerms = [];
        foreach ($UserInfo['ExtraClasses'] as $PermID => $Value) {
            $ClassPerms = self::get_permissions($PermID);
            $BonusPerms = array_merge($BonusPerms, $ClassPerms['Permissions']);
        }

        if (empty($CustomPermissions)) {
            $CustomPermissions = [];
        }

        // Combine the permissions
        return array_merge(
            $Permissions['Permissions'],
            $BonusPerms,
            $CustomPermissions
        );
    }

    public static function has_permission($UserID, $privilege) {
        $Permissions = self::get_permissions_for_user($UserID);
        return isset($Permissions[$privilege]) && $Permissions[$privilege];
    }

    public static function has_override($Level) {
        static $max;
        if (is_null($max)) {
            global $DB;
            $max = $DB->scalar('SELECT max(Level) FROM permissions');
        }
        return $Level >= $max;
    }
}
