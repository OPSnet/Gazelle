<?php

namespace Gazelle\API;

class User extends AbstractAPI {
    private $id = null;
    private $username = null;
    private $clear_tokens = false;

    public function run() {
        if (isset($_GET['user_id'])) {
            $this->id = intval($_GET['user_id']);
        }
        else if (isset($_GET['username'])) {
            $this->username = $_GET['username'];
        }
        else {
            json_error("Need to supply either user_id or username");
        }

        if (isset($_GET['clear_tokens'])) {
            $this->clear_tokens = true;
        }

        switch ($_GET['req']) {
            case 'enable':
                return $this->enableUser();
                break;
            case 'disable':
                return $this->disableUser();
                break;
            default:
            case 'stats':
                return $this->getUser();
                break;
        }
    }

    private function getUser() {
        $where = ($this->id !== null) ? "um.ID = ?" : "um.Username = ?";
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
            INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
            INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
            LEFT JOIN users_levels AS ul ON (ul.UserID = um.ID)
            LEFT JOIN user_bonus AS ub ON (ub.user_id = um.ID)
            WHERE
                {$where}", ($this->id !== null) ? $this->id : $this->username);

        $user = self::$db->next_record(MYSQLI_ASSOC, ['IRCKey', 'Paranoia']);
        if (empty($user['Username'])) {
            json_error("User not found");
        }

        $user['SecondaryClasses'] = array_map("intval", explode(",", $user['SecondaryClasses']));
        foreach (['ID', 'Uploaded', 'Downloaded', 'Class', 'Level'] as $key) {
            $user[$key] = intval($user[$key]);
        }
        $user['Paranoia'] = unserialize_array($user['Paranoia']);

        $user['Ratio'] = \Format::get_ratio($user['Uploaded'], $user['Downloaded']);
        $user['DisplayStats'] = [
            'Downloaded' => \Format::get_size($user['Downloaded']),
            'Uploaded' => \Format::get_size($user['Uploaded']),
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

    private function disableUser() {
        $userMan = new \Gazelle\Manager\User;
        if ($this->id === null) {
            $user = $userMan->findByUsername($this->username);
            if (is_null($user)) {
                json_error("No user found with username {$this->username}");
            }
            $this->id = $user->id();
        }
        $userMan->disableUserList([$this->id], 'Disabled via API', \Gazelle\Manager\User::DISABLE_MANUAL);
        return ['disabled' => true, 'user_id' => $this->id, 'username' => $this->username];
    }

    private function enableUser() {
        $where = ($this->id !== null) ? "um.ID = ?" : "um.Username = ?";
        self::$db->prepared_query("
            SELECT
                um.ID,
                um.Username,
                um.IP,
                um.Enabled,
                uls.Uploaded,
                uls.Downloaded,
                um.Visible,
                ui.AdminComment,
                um.torrent_pass,
                um.RequiredRatio,
                ui.RatioWatchEnds
            FROM users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info        AS ui  ON (ui.UserID = um.ID)
            INNER JOIN user_flt          AS uf  ON (uf.user_id = um.ID)
            WHERE
                {$where}", ($this->id !== null) ? $this->id : $this->username);

        // TODO: merge this and the version in takemoderate.php
        $UpdateSet = [];
        $Cur = self::$db->next_record(MYSQLI_ASSOC, false);
        $Comment = 'Enabled via API';

        if ($this->clear_tokens) {
            $UpdateSet[] = "um.Invites = '0'";
            $UpdateSet[] = "uf.tokens = 0";
            $Comment = 'Tokens and invites reset, enabled via API';
        }

        self::$cache->increment('stats_user_count');
        $VisibleTrIp = $Cur['Visible'] && $Cur['IP'] != '127.0.0.1' ? '1' : '0';
        $tracker = new \Gazelle\Tracker;
        $tracker->update_tracker('add_user', ['id' => $this->id,
            'passkey' => $Cur['torrent_pass'], 'visible' => $VisibleTrIp]);
        if (($Cur['Downloaded'] == 0) || ($Cur['Uploaded'] / $Cur['Downloaded'] >=
            $Cur['RequiredRatio'])) {
            $UpdateSet[] = "ui.RatioWatchEnds = NULL";
            $UpdateSet[] = "um.can_leech = '1'";
            $UpdateSet[] = "ui.RatioWatchDownload = '0'";
        } else {
            if (!is_null($Cur['RatioWatchEnds'])) {
                $UpdateSet[] = "ui.RatioWatchEnds = NOW()";
                $UpdateSet[] = "ui.RatioWatchDownload = " . $Cur['Downloaded'];
                $Comment .= ' (Ratio: '.\Format::get_ratio_html($Cur['Uploaded'],
                    $Cur['Downloaded'], false).', RR: '.number_format($Cur['RequiredRatio'], 2).')';
            }
            $tracker->update_tracker('update_user', ['passkey' => $Cur['torrent_pass'],
                'can_leech' => 0]);
        }
        $UpdateSet[] = "ui.BanReason = '0'";
        $UpdateSet[] = "um.Enabled = '1'";

        $set = implode(', ', $UpdateSet);

        self::$db->prepared_query("
            UPDATE users_main AS um
            INNER users_info AS ui ON (ui.UserID = um.ID)
            INNER user_flt   AS uf ON (uf.user_id = um.ID)
            SET
                {$set},
                ui.AdminComment = CONCAT(now(), ' - ', ?, ui.AdminComment)
            WHERE
                um.ID = ?
            ", "$Comment\n\n", $Cur['ID']
        );

        return ['enabled' => true, 'user_id' => $Cur['ID'], 'username' => $Cur['Username']];
    }
}
