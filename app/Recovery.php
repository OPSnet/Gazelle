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
        $sql_header = 'SELECT recovery_id, username, token, email, announce, created_dt, updated_dt, state FROM recovery';
        $sql_footer = 'ORDER BY updated_dt DESC LIMIT ? OFFSET ?';

        switch ($state) {
            case 'CLAIMED':
                $db->prepared_query("$sql_header
                    WHERE admin_user_id = ?
                    $sql_footer
                    ", $admin_id, $limit, $offset
                );
                break;
            case 'PENDING':
                $db->prepared_query("$sql_header
                    WHERE admin_user_id IS NULL
                        AND state = ?
                    $sql_footer
                    ", $state, $limit, $offset
                );
                break;
            default:
                $db->prepared_query("$sql_header
                    WHERE state = ?
                    $sql_footer
                    ", $state, $limit, $offset
                );
                break;
        }
        return $db->to_array();
    }

    static function validate_pending($db) {
        $db->prepared_query("SELECT recovery_id
            FROM recovery r
            INNER JOIN " . RECOVERY_DB . ".users_main m ON (m.torrent_pass = r.announce)
            WHERE r.state = 'PENDING' AND r.admin_user_id IS NULL AND char_length(r.announce) = 32
            LIMIT ?
            ", RECOVERY_AUTOVALIDATE_LIMIT);
        $recover = $db->to_array();
        foreach ($recover as $r) {
            self::accept($r['recovery_id'], RECOVERY_ADMIN_ID, RECOVERY_ADMIN_NAME, $db);
        }

        $db->prepared_query("SELECT recovery_id
            FROM recovery r
            INNER JOIN " . RECOVERY_DB . ".users_main m ON (m.Email = r.email)
            WHERE r.state = 'PENDING' AND r.admin_user_id IS NULL AND locate('@', r.email) > 1
            LIMIT ?
            ", RECOVERY_AUTOVALIDATE_LIMIT);
        $recover = $db->to_array();
        foreach ($recover as $r) {
            self::accept($r['recovery_id'], RECOVERY_ADMIN_ID, RECOVERY_ADMIN_NAME, $db);
        }
    }

    static function claim ($id, $admin_id, $admin_username, $db) {
        $db->prepared_query("
            UPDATE recovery
            SET admin_user_id = ?,
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
                AND (admin_user_id IS NULL OR admin_user_id != ?)
            ", $admin_id,
                ("\r\n" . Date('Y-m-d H:i') . " claimed by $admin_username"),
                $id, $admin_id
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
            SET state = 'DENIED',
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
            ", ("\r\n" . Date('Y-m-d H:i') . " recovery denied by $admin_username"),
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

        $db->prepared_query('select InviteKey from invites where email = ?', $email);
        if ($db->record_count() > 0) {
            list($key) = $db->next_record();
            self::accept_fail($id, "invite key $key already issued to $email", $db);
            return false;
        }

        $key = db_string(\Users::make_secret());
        $db->prepared_query("
             INSERT INTO invites (InviterID, InviteKey, Email,  Reason, Expires)
             VALUES              (?,         ?,         ?,      ?,      now() + interval 1 week)
             ",                   $admin_id, $key,      $email, "Account recovery id={$id} key={$key}"
        );

        $SITE_URL  = SITE_URL;
        $mail = <<<END_EMAIL
You recently requested to recover your account from a previous tracker in
order to join Orpheus.

The information you provided was sufficient proof for confirm that you
did have in fact have an account, and consequently you have been given
an invitation.

Please note that selling invites, trading invites, and giving invites
away publicly (e.g. on a forum) is strictly forbidden. If you do any of
these things with this invitation, do not bother signing up - you will
be banned, the person who used the invite will be banned and you and they
lose your chances of ever signing up in the future.

To confirm your invite, click on the following link:

https://$SITE_URL/register.php?invite=$key

After you register, you will be able to use your account. Please take note
that if you do not use this invite in the next 3 days, it will expire. We
urge you to read the RULES and the wiki immediately after you join.

MOST IMPORTANT OF ALL:

You should read the following article: https://$SITE_URL/wiki.php?action=article&id=114

This will help you understand what you need to do to begin reseeding
your old torrents (and avoid downloading them all over again by accident,
thereby destroying your buffer).

Thank you,
Orpheus Staff
END_EMAIL;

        \Misc::send_email($email, 'Account recovery confirmation at '.SITE_NAME, $mail, 'noreply');

        $db->prepared_query("
            UPDATE recovery
            SET state = ?,
                updated_dt = now(),
                log = concat(coalesce(log, ''), ?)
            WHERE recovery_id = ?
            ", ($admin_id == RECOVERY_ADMIN_ID ? 'VALIDATED' : 'ACCEPTED'),
                ("\r\n" . Date('Y-m-d H:i') . " recovery accepted by $admin_username invite=$key"),
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

    static function search ($terms, $db) {
        $cond = [];
        $args = [];
        foreach ($terms as $t) {
            foreach ($t as $field => $value) {
                $cond[] = "$field = ?";
                $args[] = $value;
            }
        }

        $condition = implode(' AND ', $cond);
        $db->prepared_query_array("
            SELECT
                recovery_id, state, admin_user_id, created_dt, updated_dt,
                token, username, ipaddr, password_ok, email, email_clean,
                announce, screenshot, invite, info, log
            FROM recovery
            WHERE $condition
            ", $args
        );
        return $db->next_record();
    }

    static function get_candidate ($username, $db) {
        $db->prepared_query("
            SELECT
                m.torrent_pass, m.Email, m.Uploaded, m.Downloaded, m.Enabled, m.PermissionID,
                (SELECT count(t.ID) FROM " . RECOVERY_DB . ".torrents t WHERE m.ID = t.UserID) as nr_torrents,
                group_concat(DISTINCT(h.IP) ORDER BY h.ip) as ips
            FROM " . RECOVERY_DB . ".users_main m
            LEFT JOIN " . RECOVERY_DB . ".users_history_ips h ON (m.ID = h.UserID)
            WHERE m.Username = ?
            GROUP BY m.Username
            ", $username
        );
        return $db->next_record();
    }
}
