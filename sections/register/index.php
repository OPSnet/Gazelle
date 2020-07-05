<?php

$Val = new Validate;

if (!empty($_REQUEST['confirm'])) {
    // Confirm registration
    $DB->query("
        SELECT ID
        FROM users_main
        WHERE torrent_pass = '".db_string($_REQUEST['confirm'])."'
            AND Enabled = '0'");
    list($UserID) = $DB->next_record();

    if ($UserID) {
        $DB->query("
            UPDATE users_main
            SET Enabled = '1'
            WHERE ID = '$UserID'");
        $Cache->delete_value("user_info_{$UserID}");
        $Cache->increment('stats_user_count');
        include('step2.php');
    }

} elseif (OPEN_REGISTRATION || !empty($_REQUEST['invite'])) {
    $Val->SetFields('username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEX]);
    $Val->SetFields('email', true, 'email', 'You did not enter a valid email address.');
    $Val->SetFields('password', true, 'regex', 'A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer', ['regex'=>'/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']);
    $Val->SetFields('confirm_password', true, 'compare', 'Your passwords do not match.', ['comparefield' => 'password']);
    $Val->SetFields('readrules', true, 'checkbox', 'You did not select the box that says you will read the rules.');
    $Val->SetFields('readwiki', true, 'checkbox', 'You did not select the box that says you will read the wiki.');
    $Val->SetFields('agereq', true, 'checkbox', 'You did not select the box that says you are 13 years of age or older.');
    //$Val->SetFields('captcha', true, 'string', 'You did not enter a captcha code.', array('minlength' => 6, 'maxlength' => 6));

    if (!empty($_POST['submit'])) {
        // User has submitted registration form
        $Err = $Val->ValidateForm($_REQUEST);
        /*
        if (!$Err && strtolower($_SESSION['captcha']) != strtolower($_REQUEST['captcha'])) {
            $Err = 'You did not enter the correct captcha code.';
        }
        */
        if (!$Err) {
            // Don't allow a username of "0" or "1" due to PHP's type juggling
            if (trim($_POST['username']) == '0' || trim($_POST['username']) == '1') {
                $Err = 'You cannot have a username of "0" or "1".';
            }

            $DB->query("
                SELECT COUNT(ID)
                FROM users_main
                WHERE Username LIKE '".db_string(trim($_POST['username']))."'");
            list($UserCount) = $DB->next_record();

            if ($UserCount) {
                $Err = 'There is already someone registered with that username.';
                $_REQUEST['username'] = '';
            }

            if ($_REQUEST['invite']) {
                $DB->query("
                    SELECT InviterID, Email, Reason
                    FROM invites
                    WHERE InviteKey = '".db_string($_REQUEST['invite'])."'");
                if (!$DB->has_results()) {
                    $Err = 'Invite does not exist.';
                    $InviterID = 0;
                } else {
                    list($InviterID, $InviteEmail, $InviteReason) = $DB->next_record(MYSQLI_NUM, false);
                }
            } else {
                $InviterID = 0;
                $InviteEmail = $_REQUEST['email'];
                $InviteReason = '';
            }
        }

        if (!$Err) {
            $torrent_pass = randomString();

            // Previously SELECT COUNT(ID) FROM users_main, which is a lot slower.
            $DB->query("
                SELECT ID
                FROM users_main
                LIMIT 1");
            $UserCount = $DB->record_count();
            if ($UserCount == 0) {
                $NewInstall = true;
                $Class = SYSOP;
                $Enabled = '1';
            } else {
                $NewInstall = false;
                $Class = USER;
                $Enabled = '0';
            }

            $DB->prepared_query('
                INSERT INTO users_main
                       (Username, Email, PassHash, torrent_pass, IP, PermissionID, Enabled, Invites, ipcc)
                VALUES (?,        ?,     ?,        ?,            ?,  ?,            ?,       ?,       ?)
                ',
                    trim($_POST['username']),
                    trim($_POST['email']),
                    Users::make_password_hash($_POST['password']),
                    $torrent_pass,
                    $_SERVER['REMOTE_ADDR'],
                    $Class,
                    $Enabled,
                    STARTING_INVITES,
                    Tools::geoip($_SERVER['REMOTE_ADDR'])
            );
            $UserID = $DB->inserted_id();

            // User created, delete invite. If things break after this point, then it's better to have a broken account to fix than a 'free' invite floating around that can be reused
            $DB->prepared_query('
                DELETE FROM invites
                WHERE InviteKey = ?
                ', $_REQUEST['invite']
            );

            $DB->prepared_query('
                INSERT INTO user_bonus
                       (user_id)
                VALUES (?)
                ', $UserID
            );

            $DB->prepared_query('
                INSERT INTO user_flt
                       (user_id)
                VALUES (?)
                ', $UserID
            );

            $DB->prepared_query('
                INSERT INTO users_leech_stats
                       (UserID, Uploaded)
                VALUES (?,      ?)
                ',
                    $UserID,
                    STARTING_UPLOAD
            );

            $StyleID = $DB->scalar("
                SELECT ID
                FROM stylesheets
                WHERE `Default` = '1'
            ");
            $AuthKey = randomString();

            if ($InviteReason !== '') {
                $InviteReason = sqltime()." - $InviteReason";
            }
            $DB->prepared_query('
                INSERT INTO users_info
                       (UserID, StyleID, AuthKey, Inviter, AdminComment)
                VALUES (?,      ?,       ?,       ?,       ?)
                ', $UserID, $StyleID, $AuthKey, $InviterID, $InviteReason
            );
            $DB->prepared_query('
                INSERT INTO users_history_ips
                       (UserID, IP)
                VALUES (?,      ?)
                ', $UserID, $_SERVER['REMOTE_ADDR']
            );
            $DB->prepared_query('
                INSERT INTO users_notifications_settings
                       (UserID)
                VALUES (?)
                ', $UserID
            );
            $DB->prepared_query('
                INSERT INTO users_history_emails
                       (UserID, Email, IP)
                VALUES (?,      ?,     ?)
                ', $UserID, trim($_REQUEST['email']), $_SERVER['REMOTE_ADDR']
            );

            if ($_REQUEST['email'] != $InviteEmail) {
                $DB->prepared_query('
                    INSERT INTO users_history_emails
                           (UserID, Email, IP, Time)
                    VALUES (?,      ?,     ?,  now())
                    ', $UserID, trim($InviteEmail), $_SERVER['REMOTE_ADDR']
                );
            }

            $DB->prepared_query("
                UPDATE referral_users
                    SET UserID = ?,
                        Joined = ?,
                        Active = 1,
                        InviteKey = ''
                WHERE InviteKey = ?",
                $UserID, sqltime(), $_REQUEST['invite']);

            // Manage invite trees, delete invite

            if ($InviterID !== 0) {
                $DB->query("
                    SELECT TreePosition, TreeID, TreeLevel
                    FROM invite_tree
                    WHERE UserID = '$InviterID'");
                list($InviterTreePosition, $TreeID, $TreeLevel) = $DB->next_record();

                // If the inviter doesn't have an invite tree
                // Note: This should never happen unless you've transferred from another database, like What.CD did
                if (!$DB->has_results()) {
                    $DB->query("
                        SELECT MAX(TreeID) + 1
                        FROM invite_tree");
                    list($TreeID) = $DB->next_record();

                    $DB->query("
                        INSERT INTO invite_tree
                            (UserID, InviterID, TreePosition, TreeID, TreeLevel)
                        VALUES ('$InviterID', '0', '1', '$TreeID', '1')");

                    $TreePosition = 2;
                    $TreeLevel = 2;
                } else {
                    $DB->query("
                        SELECT TreePosition
                        FROM invite_tree
                        WHERE TreePosition > '$InviterTreePosition'
                            AND TreeLevel <= '$TreeLevel'
                            AND TreeID = '$TreeID'
                        ORDER BY TreePosition
                        LIMIT 1");
                    list($TreePosition) = $DB->next_record();

                    if ($TreePosition) {
                        $DB->query("
                            UPDATE invite_tree
                            SET TreePosition = TreePosition + 1
                            WHERE TreeID = '$TreeID'
                                AND TreePosition >= '$TreePosition'");
                    } else {
                        $DB->query("
                            SELECT TreePosition + 1
                            FROM invite_tree
                            WHERE TreeID = '$TreeID'
                            ORDER BY TreePosition DESC
                            LIMIT 1");
                        list($TreePosition) = $DB->next_record();
                    }
                    $TreeLevel++;

                    // Create invite tree record
                    $DB->query("
                        INSERT INTO invite_tree
                            (UserID, InviterID, TreePosition, TreeID, TreeLevel)
                        VALUES
                            ('$UserID', '$InviterID', '$TreePosition', '$TreeID', '$TreeLevel')");
                }
            } else { // No inviter (open registration)
                $DB->query("
                    SELECT MAX(TreeID)
                    FROM invite_tree");
                list($TreeID) = $DB->next_record();
                $TreeID++;
                $InviterID = 0;
                $TreePosition = 1;
                $TreeLevel = 1;
            }

            $message = G::$Twig->render('emails/new_registration.twig', [
                'Username' => $_REQUEST['username'],
                'TorrentKey' => $torrent_pass,
                'SITE_NAME' => SITE_NAME,
                'SITE_URL' => SITE_URL
            ]);

            Misc::send_email($_REQUEST['email'], 'New account confirmation at '.SITE_NAME, $message, 'noreply');
            Tracker::update_tracker('add_user', ['id' => $UserID, 'passkey' => $torrent_pass]);
            $Sent = 1;
        }
    } elseif ($_GET['invite']) {
        // If they haven't submitted the form, check to see if their invite is good
        $DB->query("
            SELECT InviteKey
            FROM invites
            WHERE InviteKey = '".db_string($_GET['invite'])."'");
        if (!$DB->has_results()) {
            error('Invite not found!');
        }
    }

    include('step1.php');

} elseif (!OPEN_REGISTRATION) {
    if (isset($_GET['welcome'])) {
        include('code.php');
    } else {
        include('closed.php');
    }
}
