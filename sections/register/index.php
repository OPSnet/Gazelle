<?php

if (!empty($_REQUEST['confirm'])) {
    // Confirm registration
    $UserID = $DB->scalar("
        SELECT ID
        FROM users_main
        WHERE Enabled = '0'
            AND torrent_pass = ?
        ", $_REQUEST['confirm']
    );

    if ($UserID) {
        $DB->prepared_query("
            UPDATE users_main SET
                Enabled = '1'
            WHERE ID = ?
            ", $UserID
        );
        $Cache->delete_value("user_info_{$UserID}");
        $Cache->increment('stats_user_count');
        require('step2.php');
    }

} elseif (OPEN_REGISTRATION || !empty($_REQUEST['invite'])) {

    $Val = new Validate;
    $Val->SetFields('username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEX]);
    $Val->SetFields('email', true, 'email', 'You did not enter a valid email address.');
    $Val->SetFields('password', true, 'regex', 'A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer', ['regex'=>'/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']);
    $Val->SetFields('confirm_password', true, 'compare', 'Your passwords do not match.', ['comparefield' => 'password']);
    $Val->SetFields('readrules', true, 'checkbox', 'You did not select the box that says you will read the rules.');
    $Val->SetFields('readwiki', true, 'checkbox', 'You did not select the box that says you will read the wiki.');
    $Val->SetFields('agereq', true, 'checkbox', 'You did not select the box that says you are 13 years of age or older.');

    if (!empty($_POST['submit'])) {
        // User has submitted registration form

        $Err = $Val->ValidateForm($_REQUEST);

        if (!$Err) {
            $username = trim($_REQUEST['username']);
            $email    = trim($_REQUEST['email']);

            // Don't allow a username of "0" or "1" due to PHP's type juggling
            if (in_array($username, ['0', '1'])) {
                $Err = 'You cannot have a username of "0" or "1".';
            }

            $found = $DB->scalar("
                SELECT 1 FROM users_main WHERE Username = ?
                ", $username
            );
            if ($found) {
                $Err = 'There is already someone registered with that username.';
                $_REQUEST['username'] = '';
            }

            if (!$_REQUEST['invite']) {
                $InviterID    = 0;
                $InviteEmail  = $email;
                $InviteReason = '';
                $InviteReason = sqltime() . " - no invite code";
            } else {
                [$InviterID, $InviteEmail, $InviteReason] = $DB->row("
                    SELECT InviterID, Email, concat(now(), ' - ', coalesce(Reason, 'standard invitation'))
                    FROM invites
                    WHERE InviteKey = ?
                    ", trim($_REQUEST['invite'])
                );
                if (!$InviterID) {
                    View::show_header('No invitation found');
                    echo G::$Twig->render('login/no-invite.twig', [
                        'static' => STATIC_SERVER,
                        'key'    => $_GET['invite']]);
                    exit;
                }
            }
        }

        if (!$Err) {
            $found = $DB->scalar("
                SELECT ID FROM users_main LIMIT 1
            ");
            if (!$found) {
                $NewInstall = true;
                $Class = SYSOP;
                $Enabled = '1';
            } else {
                $NewInstall = false;
                $Class = USER;
                $Enabled = '0';
            }

            $torrent_pass = randomString();
            $DB->prepared_query('
                INSERT INTO users_main
                       (Username, Email, PassHash, torrent_pass, IP, PermissionID, Enabled, Invites, ipcc)
                VALUES (?,        ?,     ?,        ?,            ?,  ?,            ?,       ?,       ?)
                ',
                    $username,
                    $email,
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

            $DB->prepared_query("
                INSERT INTO users_info
                       (UserID, Inviter, AdminComment, AuthKey, StyleID)
                VALUES (?,      ?,       ?,            ?,       (SELECT ID FROM stylesheets WHERE `Default` = '1'))
                ", $UserID, $InviterID, $InviteReason, randomString()
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
                ', $UserID, $email, $_SERVER['REMOTE_ADDR']
            );

            if ($email != $InviteEmail) {
                $DB->prepared_query('
                    INSERT INTO users_history_emails
                           (UserID, Email, IP, Time)
                    VALUES (?,      ?,     ?,  now())
                    ', $UserID, $InviteEmail, $_SERVER['REMOTE_ADDR']
                );
            }

            $DB->prepared_query("
                UPDATE referral_users SET
                    Joined    = now(),
                    Active    = 1,
                    InviteKey = '',
                    UserID    = ?
                WHERE InviteKey = ?
                ", $UserID, $_REQUEST['invite']
            );

            if ($InviterID) {
                $inviteTree = new Gazelle\InviteTree($InviterID);
                $inviteTree->add($UserID);
            }

            $message = G::$Twig->render('emails/new_registration.twig', [
                'Username'   => $username,
                'TorrentKey' => $torrent_pass,
                'SITE_NAME'  => SITE_NAME,
                'SITE_URL'   => SITE_URL
            ]);
            Misc::send_email($_REQUEST['email'], 'New account confirmation at '.SITE_NAME, $message, 'noreply');
            Tracker::update_tracker('add_user', ['id' => $UserID, 'passkey' => $torrent_pass]);
            $Sent = 1;
        }
    } elseif ($_GET['invite']) {
        // If they haven't submitted the form, check to see if their invite is good
        if (!$DB->scalar("
            SELECT InviteKey FROM invites WHERE InviteKey = ?
            ", $_GET['invite']
        )) {
            View::show_header('No invitation found');
            echo G::$Twig->render('login/no-invite.twig', [
                'static' => STATIC_SERVER,
                'key'    => $_GET['invite']]);
            exit;
        }
    }

    require('step1.php');

} elseif (!OPEN_REGISTRATION) {
    if (isset($_GET['welcome'])) {
        require('code.php');
    } else {
        require('closed.php');
    }
}
