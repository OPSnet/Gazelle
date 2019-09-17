<?php

namespace Gazelle\API;

class GenerateInvite extends AbstractAPI {
    public function run() {
        if (!isset($_GET['interviewer_id']) && !isset($_GET['interviewer_name'])) {
            json_error('Missing interviewer_id or interviewer_name');
        }

        if (isset($_GET['interviewer_id'])) {
            $where = "ID='".intval($_GET['interviewer_id'])."'";
        }
        else {
            $where = "Username='".db_string($_GET['interviewer_name'])."'";
        }
        $this->db->query("SELECT ID, Username FROM users_main WHERE {$where}");
        if ($this->db->record_count() === 0) {
            json_error("Could not find interviewer");
        }
        $user = $this->db->next_record();
        $interviewer_id = $user['ID'];
        $interviewer_name = $user['Username'];

        $email = (!empty($_GET['email'])) ? db_string($_GET['email']) : "";
        $expires = time_plus(60 * 60 * 24 * 3); // 3 days
        $key = db_string(make_secret());
        $reason = "Passed Interview";

        if (!empty($_GET['email'])) {
            $this->db->query("SELECT ID, Username FROM users_main WHERE Email='{$email}'");
            if ($this->db->record_count() > 0) {
                json_error("Email address already in use");
            }

            $this->db->query("SELECT * FROM invites WHERE Email='{$email}'");
            if ($this->db->record_count() > 0) {
                $key = $this->db->next_record();
                json_error("Invite code already generated for this email address");
            }
        }

        $this->db->query("
INSERT INTO invites (InviterID, InviteKey, Email, Expires, Reason)
VALUES ('{$interviewer_id}', '{$key}', '{$email}', '{$expires}', '{$reason}')");
        $site_url = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") {
            $site_url .= "s";
        }
        $site_url .= "://" . SITE_URL . "/register.php?invite={$key}";

        if (!empty($_GET['email'])) {
            include(SERVER_ROOT.'/classes/templates.class.php');
            $TPL = NEW TEMPLATE;
            $TPL->open(SERVER_ROOT.'/templates/invite.tpl');

            $TPL->set('InviterName', $interviewer_name);
            $TPL->set('InviteKey', $key);
            $TPL->set('Email', $_GET['email']);
            $TPL->set('SITE_NAME', SITE_NAME);
            $TPL->set('SITE_URL', SITE_URL);
            $TPL->set('IRC_SERVER', BOT_SERVER);
            $TPL->set('DISABLED_CHAN', BOT_DISABLED_CHAN);

            Misc::send_email($_GET['email'], 'New account confirmation at '.SITE_NAME, $TPL->get(), 'noreply');
        }

        return array("key" => $key, "invite_url" => $site_url);
    }
}
