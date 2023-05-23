<?php

namespace Gazelle\API;

class User extends AbstractAPI {
    private int $id;
    private string $username;

    public function run(): array {
        if (isset($_GET['user_id'])) {
            $this->id = (int)$_GET['user_id'];
        } else if (isset($_GET['username'])) {
            $this->username = $_GET['username'];
        } else {
            json_error("Need to supply either user_id or username");
        }
        return $this->getUser();
    }

    private function getUser(): array {
        if (isset($this->id)) {
            $cond = "um.ID = ?";
            $arg = $this->id;
        } else {
            $cond =  "um.Username = ?";
            $arg = $this->username;
        }
        self::$db->prepared_query("
            SELECT
                um.ID,
                um.Username,
                um.Enabled,
                um.IRCKey,
                uls.Uploaded,
                uls.Downloaded,
                um.PermissionID AS Class,
                um.Paranoia,
                coalesce(ub.points, 0) as BonusPoints,
                p.Name as ClassName,
                p.Level,
                GROUP_CONCAT(ul.PermissionID SEPARATOR ',') AS SecondaryClasses
            FROM users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
            LEFT JOIN users_levels AS ul ON (ul.UserID = um.ID)
            LEFT JOIN user_bonus AS ub ON (ub.user_id = um.ID)
            WHERE $cond
            ", $arg
        );

        $user = self::$db->next_record(MYSQLI_ASSOC, ['IRCKey', 'Paranoia']);
        if (empty($user['Username'])) {
            json_error("User not found");
        }

        $user['SecondaryClasses'] = array_map("intval", explode(",", $user['SecondaryClasses']));
        foreach (['ID', 'Uploaded', 'Downloaded', 'Class', 'Level'] as $key) {
            $user[$key] = intval($user[$key]);
        }
        $user['Paranoia'] = unserialize_array($user['Paranoia']);

        $user['Ratio'] = ratio($user['Uploaded'], $user['Downloaded']);
        $user['DisplayStats'] = [
            'Downloaded' => byte_format($user['Downloaded']),
            'Uploaded' => byte_format($user['Uploaded']),
            'Ratio' => $user['Ratio']
        ];
        foreach (['Downloaded', 'Uploaded', 'Ratio'] as $key) {
            if (in_array(strtolower($key), $user['Paranoia'])) {
                $user['DisplayStats'][$key] = "Hidden";
            }
        }
        $user['UserPage'] = SITE_URL . "/user.php?id={$user['ID']}";

        return $user;
    }
}
