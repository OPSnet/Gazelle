<?php

namespace Gazelle;

class Recovery {

    static function email_check ($raw) {
        $raw = strtolower(trim($raw));
        $parts = explode('@', $raw);
        if (count($parts) != 2) {
            return null;
        }
        list($lhs, $rhs) = $parts;
        if ($rhs == 'gmail.com') {
            $lhs = str_replace('.', '', $lhs);
        }
        $lhs = preg_replace('/\+.*$/', '', $lhs);
        return [$raw, "$lhs@$rhs"];
    }

    static function validate ($info) {
        $data = [];
        foreach (explode(' ', 'username email announce invite info') as $key) {
            if (!isset($info[$key])) {
                return [];
            }
            switch ($key) {
                case 'email':
                    $email = self::email_check($info['email']);
                    if (!$email) {
                        return [];
                    }
                    $data['email']       = $email[0];
                    $data['email_clean'] = $email[1];
                    break;

                default:
                    $data[$key] = trim($info[$key]);
                    break;
            }
        }
        return $data;
    }

    static function save_screenshot($upload) {
        if (!isset($upload['screen'])) {
            return [false, "File form name missing"];
        }
        $file = $upload['screen'];
        if (!isset($file['error']) || is_array($file['error'])) {
            return [false, "Never received the uploaded file."];
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return [true, null];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return [false, "File was too large, please make sure it is less than 10MB in size."];
            default:
                return [false, "There was a problem with the screenshot file."];
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return [false, "File was too large, please make sure it is less than 10MB in size."];
        }
        $filename = sha1(RECOVERY_SALT . mt_rand(0, 10000000). sha1_file($file['tmp_name']));
        $destination = sprintf('%s/%s/%s/%s/%s',
            RECOVERY_PATH, substr($filename, 0, 1), substr($filename, 1, 1), substr($filename, 2, 1), $filename
        );
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return [false, "Unable to persist your upload."];
        }
        return [true, $filename];
    }

    static function check_password($user, $pw, $db) {
        $db->prepared_query('SELECT PassHash FROM ' . RECOVERY_DB . '.users_main WHERE Username = ?', $user);
        if (!$db->has_results()) {
            return false;
        }
        list($prevhash) = $db->next_record();
        return password_verify($pw, $prevhash);
    }

    static function persist($info, $db) {
        $db->prepared_query(
            "INSERT INTO recovery (token, ipaddr, username, password_ok, email, email_clean, announce, screenshot, invite, info, state    )
                           VALUES (?,     ?,      ?,        ?,           ?,     ?,           ?,        ?,          ?,      ?,    'PENDING')",
            $info['token'],
            $info['ipaddr'],
            $info['username'],
            $info['password_ok'],
            $info['email'],
            $info['email_clean'],
            $info['announce'],
            $info['screenshot'],
            $info['invite'],
            $info['info']
        );
        return $db->affected_rows();
    }

    static function get_total($state, $admin_id, $db) {
        $state = strtoupper($state);
        switch ($state) {
            case 'CLAIMED':
                $db->prepared_query("SELECT count(*) FROM recovery WHERE state = ? and admin_user_id = ?", $state, $admin_id);
                break;
            case 'PENDING':
                $db->prepared_query("SELECT count(*) FROM recovery WHERE state = ? and (admin_user_id is null or admin_user_id != ?)", $state, $admin_id);
                break;
            default:
                $db->prepared_query("SELECT count(*) FROM recovery WHERE state = ?", strtoupper($state));
                break;
        }
        list($total) = $db->next_record();
        return $total;
    }

    static function get_list($limit, $offset, $state, $admin_id, $db) {
        $state = strtoupper($state);
        switch ($state) {
            case 'CLAIMED':
                $db->prepared_query("
                    SELECT recovery_id, username, token, email, announce, created_dt
                    FROM recovery
                    WHERE admin_user_id = ?
                    ORDER BY recovery_id
                    LIMIT ? OFFSET ?
                    ", $admin_id, $limit, $offset
                );
                break;
            case 'PENDING':
                $db->prepared_query("
                    SELECT recovery_id, username, token, email, announce, created_dt
                    FROM recovery
                    WHERE (admin_user_id is NULL OR admin_user_id != ?)
                        AND state = ?
                    ORDER BY recovery_id
                    LIMIT ? OFFSET ?
                    ", $admin_id, $state, $limit, $offset
                );
                break;
            default:
                $db->prepared_query("
                    SELECT recovery_id, username, token, email, announce, created_dt
                    FROM recovery
                    WHERE state = ?
                    ORDER BY recovery_id
                    LIMIT ? OFFSET ?
                    ", $state, $limit, $offset
                );
                break;
        }
        return $db->to_array();
    }

    static function claim ($id, $admin_id, $admin_username, $db) {
        $db->prepared_query("
            UPDATE recovery
            SET admin_user_id = ?,
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
            ", $admin_id,
                ("\r\n" . Date('Y-m-d H:i') . " claimed by $admin_username"),
                $id
        );
        return $db->affected_rows();
    }

    static function unclaim ($id, $admin_username, $db) {
        $db->prepared_query("
            UPDATE recovery
            SET admin_user_id = NULL,
                state = 'PENDING',
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
            ", ("\r\n" . Date('Y-m-d H:i') . " unclaimed by $admin_username"),
                $id
        );
        return $db->affected_rows();
    }

    static function deny ($id, $admin_id, $admin_username, $db) {
        $db->prepared_query("
            UPDATE recovery
            SET admin_user_id = ?,
                state = 'DENIED',
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
            ", $admin_id,
                ("\r\n" . Date('Y-m-d H:i') . " recovery denied by $admin_username"),
                $id
        );
        return $db->affected_rows();
    }

    static function accept_fail($id, $reason, $db) {
        $db->prepared_query("
            UPDATE recovery
            SET state = 'PENDING',
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
            ", ("\r\n" . Date('Y-m-d H:i') . " recovery failed: $reason"),
                $id
        );
    }

    static function accept ($id, $admin_id, $admin_username, $db) {
        $db->prepared_query("
            SELECT username, email_clean
            FROM recovery WHERE state != 'DENIED' AND recovery_id = ?
            ", $id
        );
        if (!$db->has_results()) {
            return false;
        }
        list ($username, $email) = $db->next_record();

        $db->prepared_query('select 1 from users_main where email = ?', $email);
        if ($db->record_count() > 0) {
            self::accept_fail($id, "an existing user $username already registered with $email", $db);
            return false;
        }

        $db->prepared_query('select 1 from invites where email = ?', $email);
        if ($db->record_count() > 0) {
            $key = $db->next_record();
            self::accept_fail($id, "invite key $key already issued to $email", $db);
            return false;
        }

        $key = db_string(\Users::make_secret());
        $db->prepared_query("
             INSERT INTO invites (InviterID, InviteKey, Email,  Expires,                 Reason)
             VALUES              (?,         ?,         ?,      now() + interval 1 week, 'Account recovery')
             ",                   $admin_id, $key,      $email
        );

        require('classes/templates.class.php');
        $TPL = new \TEMPLATE;
        $TPL->open('templates/recovery.tpl');
        $TPL->set('InviteKey', $key);
        $TPL->set('SITE_NAME', SITE_NAME);
        $TPL->set('SITE_URL',  SITE_URL);
        \Misc::send_email($email, 'Account recovery confirmation at '.SITE_NAME, $TPL->get(), 'noreply');

        $db->prepared_query("
            UPDATE recovery
            SET admin_user_id = ?,
                state = 'ACCEPTED',
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
            ", $admin_id,
                ("\r\n" . Date('Y-m-d H:i') . " recovery accepted by $admin_username"),
                $id
        );
        return true;
    }

    static function get_details ($id, $db) {
        $db->prepared_query("
            SELECT
                recovery_id, state, admin_user_id, created_dt, updated_dt,
                token, username, ipaddr, password_ok, email, email_clean,
                announce, screenshot, invite, info, log
            FROM recovery
            WHERE recovery_id = ?
            ", $id
        );
        return $db->next_record();
    }

    static function get_candidate ($username, $db) {
        $db->prepared_query("
            SELECT
                m.torrent_pass, m.Email, group_concat(DISTINCT(h.IP) ORDER BY h.ip) as ips
            FROM " . RECOVERY_DB . ".users_main m
            LEFT JOIN " . RECOVERY_DB . ".users_history_ips h ON (m.ID = h.UserID)
            WHERE m.Username = ?
            GROUP BY m.Username
            ", $username
        );
        return $db->next_record();
    }
}
