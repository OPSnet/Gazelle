<?php

class User extends AbstractAPI {
    private $user;
    
    public function run() {
        if (isset($_GET['user_id'])) {
            $where = "um.ID='".intval($_GET['user_id'])."'";
        }
        else if (isset($_GET['username'])) {
            $where = "um.Username='".db_string($_GET['username'])."'";
        }
        else {
            json_error("Need to supply either user_id or user_name");
        }
        
        $this->db->query("
SELECT
    um.ID, um.Username, um.Email, um.IRCKey, um.Uploaded, um.Downloaded, um.Paranoia, um.Enabled,
    um.Invites, um.PermissionID, um.LastAccess, p.Level as PermissionLevel, p.Name as PermissionName, ui.*
FROM users_main as um
LEFT JOIN (
    SELECT UserID, AdminComment, Donor, JoinDate, Inviter, DisableIRC, BanDate, BanReason, JoinDate
    FROM users_info
) AS ui ON ui.UserID = um.ID
LEFT JOIN (SELECT ID, Level, Name FROM permissions) AS p ON p.ID = um.PermissionID
WHERE {$where}");
        if ($this->db->record_count() === 0) {
            error('No user found');
        }
        $this->user = $this->db->next_record(MYSQLI_ASSOC, false);
        $this->user['Paranoia'] = unserialize($this->user['Paranoia']);
        
        $this->user['Ratio'] = Format::get_ratio($this->user['Uploaded'], $this->user['Downloaded']);
        $this->user['DisplayStats'] = array('Downloaded' => Format::get_size($this->user['Downloaded']),
                                            'Uploaded' => Format::get_size($this->user['Uploaded']),
                                            'Ratio' => $this->user['Ratio']);
        foreach (array('Downloaded', 'Uploaded', 'Ratio') as $key) {
            if (in_array(strtolower($key), $this->user['Paranoia'])) {
                $this->user['DisplayStats'][$key] = "Hidden";
            }
        }
        $this->user['UserPage'] = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") {
            $this->user['UserPage'] .= "s";
        }
        $this->user['UserPage'] .= "://" . SITE_URL . "/user.php?id={$this->user['ID']}";
        
        switch($_GET['req']) {
            case 'enable':
                return $this->disableUser();
                break;
            case 'disable':
                return $this->enableUser();
                break;
            default:
            case 'stats':
                return $this->getStats();
                break;
        }
    }
    
    private function getStats() {
        return $this->user;
    }
    
    private function disableUser() {
        $this->db->query("UPDATE users_main SET Enabled='2' WHERE ID='{$this->user['ID']}'");
        return array('disabled' => true, 'user_id' => $this->user['ID'], 'username' => $this->user['Username']);
    }
    
    private function enableUser() {
        $this->db->query("UPDATE users_main SET Enabled='1' WHERE ID='{$this->user['ID']}'");
        return array('enabled' => true, 'user_id' => $this->user['ID'], 'username' => $this->user['Username']);
    }
}