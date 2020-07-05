<?php

namespace Gazelle\API;

class GenerateInvite extends AbstractAPI {
    public function run() {
        if (!isset($_GET['interviewer_id']) && !isset($_GET['interviewer_name'])) {
            json_error('Missing interviewer_id or interviewer_name');
        }

        if (isset($_GET['interviewer_id'])) {
            $where = "ID";
            $param = intval($_GET['interviewer_id']);
        }
        else {
            $where = "Username";
            $param = $_GET['interview_name'];
        }
        $this->db->prepared_query("SELECT ID, Username FROM users_main WHERE {$where}=?", $param);
        if ($this->db->record_count() === 0) {
            json_error("Could not find interviewer");
        }
        $user = $this->db->next_record();
        $interviewer_id = $user['ID'];
        $interviewer_name = $user['Username'];

        $email = $_GET['email'] ?? '';
        if (!empty($_GET['email'])) {
            if ($this->db->scalar("SELECT 1 FROM users_main WHERE Email = ?", $email)) {
                json_error("Email address already in use");
            }

            if ($this->db->scalar("SELECT 1 FROM invites WHERE Email = ?", $email)) {
                json_error("Invite code already generated for this email address");
            }
        }

        $key = randomString();
        $this->db->prepared_query(
            "INSERT INTO invites
                    (InviterID, InviteKey, Email, Reason, Expires)
            VALUES  (?,         ?,         ?,     ?,      now() + INTERVAL 3 DAY)",
            $interviewer_id, $key, $email, "Passed Interview"
        );
        $site_url = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") {
            $site_url .= "s";
        }
        $site_url .= "://" . SITE_URL . "/register.php?invite={$key}";

        if (!empty($_GET['email'])) {
            $body = $this->twig->render('emails/invite.twig', [
                'InviterName' => $interviewer_name,
                'InviteKey' => $key,
                'Email' => $_GET['email'],
                'SITE_NAME' => SITE_NAME,
                'SITE_URL' => SITE_URL,
                'IRC_SERVER' => BOT_SERVER,
                'DISABLED_CHAN' => BOT_DISABLED_CHAN
            ]);

            Misc::send_email($_GET['email'], 'New account confirmation at '.SITE_NAME, $body, 'noreply');
        }

        return ["key" => $key, "invite_url" => $site_url];
    }
}
