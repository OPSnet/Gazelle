<?php

namespace Gazelle;

use Gazelle\Util\Mail;

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
                continue;
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

        $key = randomString();
        $db->prepared_query("
             INSERT INTO invites (InviterID, InviteKey, Email,  Reason, Expires)
             VALUES              (?,         ?,         ?,      ?,      now() + interval 1 week)
             ",                   $admin_id, $key,      $email, "Account recovery id={$id} key={$key}"
        );
        global $Twig;
        (new Mail)->send($email, 'Account recovery confirmation at ' . SITE_NAME,
            $Twig->render('email/recovery.twig', [
                'invite_key' => $key,
            ])
        );

        $db->prepared_query("
            UPDATE recovery SET
                updated_dt = now(),
                state = ?,
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
        $db->prepared_query("
            SELECT
                recovery_id, state, admin_user_id, created_dt, updated_dt,
                token, username, ipaddr, password_ok, email, email_clean,
                announce, screenshot, invite, info, log
            FROM recovery
            WHERE $condition
            ", ...$args
        );
        return $db->next_record();
    }

    static private function get_candidate_sql () {
        return sprintf('
            SELECT m.Username, m.torrent_pass, m.Email, m.Uploaded, m.Downloaded, m.Enabled, m.PermissionID, m.ID as UserID,
                (SELECT count(t.ID) FROM %s.torrents t WHERE m.ID = t.UserID) as nr_torrents,
                group_concat(DISTINCT(h.IP) ORDER BY h.ip) as ips
            FROM %s.users_main m LEFT JOIN %s.users_history_ips h ON (m.ID = h.UserID)
            ', RECOVERY_DB, RECOVERY_DB, RECOVERY_DB
        );
    }

    static function get_candidate ($username, $db) {
        $db->prepared_query(self::get_candidate_sql() . "
            WHERE m.Username LIKE ? GROUP BY m.Username
            ", $username
        );
        return $db->next_record();
    }

    static function get_candidate_by_username ($username, $db) {
        $db->prepared_query(self::get_candidate_sql() . "
            WHERE m.Username LIKE ? GROUP BY m.Username
            ", $username
        );
        return $db->to_array();
    }

    static function get_candidate_by_announce ($announce, $db) {
        $db->prepared_query(self::get_candidate_sql() . "
            WHERE m.torrent_pass LIKE ? GROUP BY m.torrent_pass
            ", $announce
        );
        return $db->to_array();
    }

    static function get_candidate_by_email ($email, $db) {
        $db->prepared_query(self::get_candidate_sql() . "
            WHERE m.Email LIKE ? GROUP BY m.Email
            ", $email
        );
        return $db->to_array();
    }

    static function get_candidate_by_id ($id, $db) {
        $db->prepared_query(self::get_candidate_sql() . "
            WHERE m.ID = ? GROUP BY m.ID
            ", $id
        );
        return $db->to_array();
    }

    static private function get_user_details_sql ($schema = null) {
        if ($schema) {
            $permission_t = "$schema.permissions";
            $users_main_t = "$schema.users_main";
            $torrents_t   = "$schema.torrents";
        }
        else {
            $permission_t = 'permissions';
            $users_main_t = 'users_main';
            $torrents_t   = 'torrents';
        }
        return "
            SELECT u.ID, u.Username, u.Email, u.torrent_pass, p.Name as UserClass, count(t.ID) as nr_torrents
            FROM $users_main_t u
            INNER JOIN $permission_t p ON (p.ID = u.PermissionID)
            LEFT JOIN $torrents_t t ON (t.UserID = u.ID)
            WHERE u.ID = ?
            GROUP BY u.ID, u.Username, u.Email, u.torrent_pass, p.Name
        ";
    }

    static public function get_pair_confirmation($prev_id, $curr_id, $db) {
        $db->prepared_query(self::get_user_details_sql(RECOVERY_DB), $prev_id);
        $prev = $db->next_record();
        $db->prepared_query(self::get_user_details_sql(), $curr_id);
        $curr = $db->next_record();
        return [$prev, $curr];
    }

    public static function is_mapped($ID, $db) {
        $db->prepared_query(sprintf("SELECT MappedID AS ID FROM %s.%s WHERE UserID = ?", RECOVERY_DB, RECOVERY_MAPPING_TABLE), $ID);
        return $db->to_array();
    }

    public static function is_mapped_local($ID, $db) {
        $db->prepared_query(sprintf("SELECT UserID AS ID FROM %s.%s WHERE MappedID = ?", RECOVERY_DB, RECOVERY_MAPPING_TABLE), $ID);
        return $db->to_array();
    }

    public static function map_to_previous($ops_user_id, $prev_user_id, $admin_username, $db) {
        $db->prepared_query(
            sprintf("INSERT INTO %s.%s (UserID, MappedID) VALUES (?, ?)", RECOVERY_DB, RECOVERY_MAPPING_TABLE),
            $prev_user_id, $ops_user_id
        );
        if ($db->affected_rows() != 1) {
            return false;
        }

        /* staff note */
        $db->prepared_query("
            UPDATE users_info
            SET AdminComment = CONCAT(?, AdminComment)
            WHERE UserID = ?
            ", sqltime() . " mapped to previous id $prev_user_id by $admin_username\n\n", $ops_user_id
        );
        return true;
    }

    static function boost_upload ($db, $cache) {
        $sql = sprintf("
            SELECT HIST.Username, HIST.MappedID, HIST.UserID, HIST.Uploaded, HIST.Downloaded, HIST.Bounty, HIST.nr_torrents, HIST.userclass,
                round(
                    CASE
                        WHEN HIST.nr_torrents >= 500                            THEN (1.5 * (500 + ((HIST.nr_torrents - 500) * 0.5)) - 3) * pow(1024, 3)
                        WHEN HIST.nr_torrents >=  50 AND HIST.nr_torrents < 500 THEN (1.5 * (100 + ((HIST.nr_torrents -  50) * 0.8)) - 3) * pow(1024, 3)
                        WHEN HIST.nr_torrents >=   5 AND HIST.nr_torrents <  50 THEN (1.5 *  (25 + ((HIST.nr_torrents -   5) * 0.5)) - 3) * pow(1024, 3)
                        WHEN HIST.nr_torrents >=   1 AND HIST.nr_torrents <   5 THEN (1.5 *   (5 +   HIST.nr_torrents)               - 3) * pow(1024, 3)
                        ELSE 0.0
                    END + (HIST.Downloaded + HIST.bounty) * 0.5,
                    0
                ) as new_up
            FROM (
                SELECT uam.MappedID, uam.UserID,
                    u.Username,
                    u.Uploaded,
                    u.Downloaded,
                    lower(irc.userclass) as userclass,
                    coalesce(r.Bounty, 0) as Bounty,
                    count(t.ID) AS nr_torrents
                FROM       %s.users_main u
                INNER JOIN %s.users_info ui ON (ui.UserID = u.ID)
                LEFT  JOIN %s.torrents t    ON ( t.UserID = u.ID)
                LEFT  JOIN (
                    SELECT UserID, sum(bounty) as Bounty
                    FROM %s.requests_votes
                    GROUP BY UserID
                ) r ON (r.UserID = u.ID)
                INNER JOIN %s.%s uam ON (uam.UserID = u.ID)
                LEFT  JOIN %s.%s irc ON (irc.UserID = u.ID)
                LEFT  JOIN  users_buffer_log ubl ON (ubl.aplid = u.ID)
                WHERE NOT uam.buffer
                    AND uam.UserID > 1
                    AND ubl.aplid IS NULL
                GROUP BY u.id
            ) HIST
            LIMIT ?
        ",  RECOVERY_DB,
            RECOVERY_DB,
            RECOVERY_DB,
            RECOVERY_DB,
            RECOVERY_DB, RECOVERY_MAPPING_TABLE,
            RECOVERY_DB, RECOVERY_IRC_TABLE
        );
        $db->prepared_query($sql, RECOVERY_BUFFER_REASSIGN_LIMIT);

        $rescale = [
            'member'        =>  10.0 * pow(1024, 3),
            'poweruser'     =>  25.0 * pow(1024, 3),
            'elite'         => 100.0 * pow(1024, 3),
            'torrentmaster' => 500.0 * pow(1024, 3),
            'powertm'       => 500.0 * pow(1024, 3),
            'elitetm'       => 500.0 * pow(1024, 3)
        ];

        $results = $db->to_array();
        foreach ($results as $r) {
            list($username, $ops_user_id, $apl_user_id, $uploaded, $downloaded, $bounty, $nr_torrents, $irc_userclass, $final) = $r;

            /* close the gate */
            $db->prepared_query(sprintf("
                UPDATE %s.users_apl_mapping
                SET buffer = true
                WHERE MappedID = ?
                ", RECOVERY_DB
                )
                , $ops_user_id
            );

            /* upscale from IRC activity */
            $irc_change = '';
            if (array_key_exists($irc_userclass, $rescale)) {
                $rescale_uploaded = 0.0 + $rescale[$irc_userclass];
                if ($rescale_uploaded > $final) {
                    $irc_message = "Upscaled from $uploaded to $rescale_uploaded from final irc userclass $irc_userclass";
                    $final += 1.5 * ($rescale_uploaded - $final);
                    $irc_change = "\n\nThe above buffer calculation takes into account your final recorded userclass on IRC '$irc_userclass'";
                }
                else {
                    $irc_message = "No change from logged irc userclass $irc_userclass";
                }
            }
            else {
                $irc_message = 'never joined #APOLLO';
            }

            $reclaimed = self::reclaimPreviousUpload($db, $ops_user_id);
            if ($reclaimed == -1) {
                $reclaimMsg = "There were no torrents available to recover from the backup.";
            } else {
                $reclaimMsg = "Number of torrents found in the backup: $reclaimed. You will now receive bonus points for these torrents if you begin to seed them again.";
            }

            $uploaded_fmt   = \Format::get_size($uploaded);
            $downloaded_fmt = \Format::get_size($downloaded);
            $bounty_fmt     = \Format::get_size($bounty);
            $final_fmt      = \Format::get_size($final);

            $admin_comment = sprintf("%s - Upload stats recovery raw: Up=%d Down=%d Bounty=%d Torrents=%d IRC=%s"
                . "\nformatted: U=%s D=%s B=%s Final=%s (%d) APL_ID=%d RESCALE=%s reclaim=$reclaimed\n\n",
                $username, $uploaded, $downloaded, $bounty, $nr_torrents, $irc_userclass,
                $uploaded_fmt, $downloaded_fmt, $bounty_fmt, $final_fmt, $final, $apl_user_id, $irc_message
            );

            /* no buffer for you if < 1MB */
            if ($final >= 1.0) {
                $to = \Users::user_info($ops_user_id);
                if (RECOVERY_BUFFER) {
                    $Body = <<<END_MSG
Dear {$to['Username']},

Your activity on the previous site has been rewarded.
$reclaimMsg
Your details are as follows:

[*] Torrents uploaded: {$nr_torrents}
[*] Downloaded: {$downloaded_fmt}
[*] Bounty: {$bounty_fmt} (requests created and voted upon).
[*] ... magic ...
[*] Buffer: $final_fmt
END_MSG;
                    if (strlen($irc_change)) {
                        $Body .= "$irc_change\n";
                    }
                    $Body .= <<<END_MSG

This amount has been added to your existing Uploaded stats.  Don't sit on this buffer,
go out and use it. You never know what tomorrow will bring.

<3
--OPS Staff
END_MSG;
                } else {
                $Body = <<<END_MSG
Dear {$to['Username']},

$reclaimMsg

--OPS Staff
END_MSG;
                }
                (new Manager\User)->sendPM($ops_user_id, 0, "Your buffer stats have been updated", $Body);
            }

            /* insert this first to avoid a potential reallocation */
            $db->prepared_query("
                INSERT INTO users_buffer_log
                       (opsid, aplid, uploaded, downloaded, bounty, nr_torrents, userclass, final)
                VALUES (?,     ?,     ?,        ?,          ?,      ?,           ?,         ?)
                ", $ops_user_id, $apl_user_id, $uploaded, $downloaded, $bounty, $nr_torrents, $irc_userclass,
                    RECOVERY_BUFFER ? $final : 0
            );

            /* staff note */
            $db->prepared_query("
                UPDATE users_info
                SET AdminComment = CONCAT(?, AdminComment)
                WHERE UserID = ?
                ", $admin_comment, $ops_user_id
            );

            /* buffer */
            if (RECOVERY_BUFFER) {
                $db->prepared_query("
                    UPDATE users_leech_stats
                    SET Uploaded = Uploaded + ?
                    WHERE UserID = ?
                    ", $final, $ops_user_id
                );
            }
            $cache->delete_value('user_stats_' . $ops_user_id);
        }
    }

    public static function reclaimPreviousUpload($db, $userId) {
        $db->prepared_query(
            sprintf('
                UPDATE torrents curr
                INNER JOIN %s.torrents prev USING (ID)
                SET
                    curr.UserID = ?
                WHERE prev.UserID = (
                    SELECT userid
                    FROM %s.%s
                    WHERE MappedID = ?
                        AND mapped = 0
                )
                ', RECOVERY_DB, RECOVERY_DB, RECOVERY_MAPPING_TABLE
            ),  $userId, $userId
        );
        $reclaimed = $db->affected_rows();
        if ($reclaimed == 0) {
            $reclaimed = -1;
        }
        $db->prepared_query(
            sprintf('
                UPDATE %s.users_apl_mapping SET mapped = ? WHERE MappedID = ?
                ', RECOVERY_DB
            ), $reclaimed, $userId
        );
        return $reclaimed;
    }
}
