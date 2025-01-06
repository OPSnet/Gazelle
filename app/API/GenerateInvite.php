<?php

namespace Gazelle\API;

use Gazelle\Util\Mail;

class GenerateInvite extends AbstractAPI {
    public function run(): array {
        $userMan = new \Gazelle\Manager\User();
        $interviewer = null;
        if (isset($_GET['interviewer_id'])) {
            $interviewer = $userMan->findById((int)$_GET['interviewer_id']);
        } elseif (isset($_GET['interviewer_name'])) {
            $interviewer = $userMan->findByUsername($_GET['interviewer_name']);
        } else {
            json_error('Missing interviewer_id or interviewer_name');
        }
        if (is_null($interviewer)) {
            json_error("Could not find interviewer");
        }

        $email = trim($_GET['email'] ?? '');
        if (empty($email)) {
            json_error("Missing email address");
        } else {
            if (self::$db->scalar("SELECT 1 FROM users_main WHERE Email = ?", $email)) {
                json_error("Email address already in use");
            }
            if (self::$db->scalar("SELECT 1 FROM invites WHERE Email = ?", $email)) {
                json_error("Invite code already generated for this email address");
            }
        }

        $key = randomString();
        self::$db->prepared_query(
            "INSERT INTO invites
                    (InviterID, InviteKey, Email, Reason, Expires)
            VALUES  (?,         ?,         ?,     ?,      now() + INTERVAL 3 DAY)",
            $interviewer->id(), $key, $email, "Passed Interview"
        );
        if (!empty($_GET['email'])) {
            (new Mail())->send($email, 'New account confirmation at ' . SITE_NAME,
                self::$twig->render('email/invite-interviewer.twig', [
                    'inviter_name' => $interviewer->username(),
                    'inviter_key' => $key,
                    'email' => $email,
                ])
            );
        }

        return [
            "key" => $key,
            "invite_url" => SITE_URL . "/register.php?invite={$key}"
        ];
    }
}
