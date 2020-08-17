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

            $creator = new Gazelle\UserCreator;
            $creator->setUserName($username)
                ->setEmail($email)
                ->setPassword($_POST['password'])
                ->setIpaddr($_SERVER['REMOTE_ADDR']);
            if ($_REQUEST['invite']) {
                $creator->setInviteKey($_REQUEST['invite']);
            }

            try {
                $user = $creator->create();
            }
            catch (Gazelle\UserCreatorException $e) {
                switch ($e->getMessage()) {
                    case 'email':
                        $Err = 'No email address given';
                        break;
                    case 'ipaddr':
                        $Err = 'No IP address given';
                        break;
                    case 'invitation':
                        View::show_header('No invitation found');
                        echo G::$Twig->render('login/no-invite.twig', [
                            'static' => STATIC_SERVER,
                            'key'    => $_GET['invite']
                        ]);
                        exit;
                        break;
                    case 'password':
                        $Err = 'No password given';
                        break;
                    case 'username':
                        $Err = 'No username given';
                        break;
                    case 'username-invalid':
                        $Err = 'Specified username is forbidden';
                        break;
                }
            }

            if (!$Err) {
                Misc::send_email(
                    $creator->email(),
                    'New account confirmation at '.SITE_NAME,
                    G::$Twig->render('emails/new_registration.twig', [
                        'Username'   => $username,
                        'TorrentKey' => $creator->announceKey(),
                        'SITE_NAME'  => SITE_NAME,
                        'SITE_URL'   => SITE_URL
                    ]),
                    'noreply'
                );
                Tracker::update_tracker('add_user', ['id' => $UserID, 'passkey' => $torrent_pass]);
                $Sent = 1;
                $NewInstall = $creator->newInstall();
            }
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
