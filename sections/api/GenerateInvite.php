<?php

require_once(SERVER_ROOT.'/sections/api/AbstractAPI.php');

class GenerateInvite extends AbstractAPI {
    public function run() {
        $interviewer = db_string($_GET['interviewer']);
        $email = db_string($_GET['email']);
        $expires = time_plus(60 * 60 * 24 * 3); // 3 days
        $key = db_string(make_secret());
        $reason = "Passed Interview";
    
        $this->db->query("SELECT ID, Username FROM users_main WHERE Email='{$email}'");
        if ($this->db->record_count() > 0) {
            error("Email address already in use");
        }
        
        $this->db->query("SELECT * FROM invites WHERE Email='{$email}'");
        if ($this->db->record_count() > 0) {
            $key = $this->db->next_record();
            error("Invite code already generated for this email address");
        }
        
        $this->db->query("SELECT ID FROM users_main WHERE Username='{$interviewer}'");
        if ($this->db->record_count() === 0) {
            error("Could not find interviewer");
        }
        $user = $this->db->next_record();
        
        $interviewer_id = $user['ID'];
        $this->db->query("
INSERT INTO invites (InviterID, InviteKey, Email, Expires, Reason)
VALUES ('{$interviewer_id}', '{$key}', '{$email}', '{$expires}', '{$reason}')");
        return array("key" => $key);
    }
}