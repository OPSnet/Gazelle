<?php

use Gazelle\Util\Mail;

// needs to be defined here for fall-through to step1
$Val = new Gazelle\Util\Validator;
$Val->setFields([
    ['username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEX]],
    ['email', true, 'email', 'You did not enter a valid email address.'],
    ['password', true, 'regex', 'A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer', ['regex'=>'/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']],
    ['confirm_password', true, 'compare', 'Your passwords do not match.', ['comparefield' => 'password']],
    ['readrules', true, 'checkbox', 'You did not select the box that says you will read the rules.'],
    ['readwiki', true, 'checkbox', 'You did not select the box that says you will read the wiki.'],
    ['agereq', true, 'checkbox', 'You did not select the box that says you are 13 years of age or older.'],
]);

if (!empty($_REQUEST['confirm'])) {
    // Confirm registration
    $user = (new Gazelle\Manager\User)->findById(
        (int)$DB->scalar("
            SELECT ID FROM users_main WHERE Enabled = '0' AND torrent_pass = ?
        ", $_REQUEST['confirm']
        )
    );

    if ($user) {
        $DB->prepared_query("
            UPDATE users_main SET
                Enabled = '1'
            WHERE ID = ?
            ", $user->id()
        );
        $user->flush();
        $Cache->increment('stats_user_count');
        require('step2.php');
    }

} elseif (OPEN_REGISTRATION || !empty($_REQUEST['invite'])) {
    if (!empty($_POST['submit'])) {
        // User has submitted registration form

        $Err = $Val->validate($_REQUEST) ? false : $Val->errorMessage();
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
                (new Mail)->send($user->email(), 'New account confirmation at '.SITE_NAME,
                    $Twig->render('email/registration.twig', [
                        'username'     => $username,
                        'announce_key' => $user->announceKey(),
                    ])
                );
                (new Gazelle\Manager\User)->sendPM( $user->id(), 0,
                    "Welcome to " . SITE_NAME,
                    $Twig->render('user/welcome.twig', [
                        'username'     => $username,
                        'announce_url' => $user->announceUrl(),
                    ])
                );
                (new Gazelle\Tracker)->update_tracker('add_user', ['id' => $user->id(), 'passkey' => $user->announceKey()]);
                $Sent = 1;
            }
            catch (Gazelle\Exception\UserCreatorException $e) {
                switch ($e->getMessage()) {
                    case 'email':
                        $Err = 'No email address given';
                        break;
                    case 'ipaddr':
                        $Err = 'No IP address given';
                        break;
                    case 'invitation':
                        View::show_header('No invitation found');
                        echo $Twig->render('login/no-invite.twig', [
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
            echo $Twig->render('login/no-invite.twig', [
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
