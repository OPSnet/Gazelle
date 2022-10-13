<?php

if (isset($_REQUEST['confirm'])) {
    // Confirm registration
    $user = (new Gazelle\Manager\User)->findByAnnounceKey($_REQUEST['confirm']);
    if ($user && $user->setUpdate('Enabled', '1')->modify()) {
        $user->flush();
        $Cache->increment('stats_user_count');
        echo $Twig->render('register/complete.twig');
    }

} elseif (OPEN_REGISTRATION || isset($_REQUEST['invite'])) {
    if ($_REQUEST['invite']) {
        if (!(new Gazelle\Manager\Invite)->inviteExists($_GET['invite'])) {
            echo $Twig->render('register/no-invite.twig');
            exit;
        }
    }

    $validator = new Gazelle\Util\Validator;
    $validator->setFields([
        ['username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEXP]],
        ['email', true, 'email', 'You did not enter a valid email address.'],
        ['password', true, 'regex', 'A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer', ['regex'=>'/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']],
        ['confirm_password', true, 'compare', 'Your passwords do not match.', ['comparefield' => 'password']],
        ['readrules', true, 'checkbox', 'You did not select the box that says you will read the rules.'],
        ['readwiki', true, 'checkbox', 'You did not select the box that says you will read the wiki.'],
        ['agereq', true, 'checkbox', 'You did not select the box that says you are 13 years of age or older.'],
    ]);

    if (isset($_POST['submit'])) {
        $error = $validator->validate($_POST) ? false : $validator->errorMessage();
        if (!$error) {
            $username = trim($_POST['username']);
            $email    = trim($_POST['email']);

            $creator = new Gazelle\UserCreator;
            $creator->setUsername($username)
                ->setEmail($email)
                ->setPassword($_POST['password'])
                ->setIpaddr($_SERVER['REMOTE_ADDR']);
            if ($_POST['invite']) {
                $creator->setInviteKey($_POST['invite']);
            }

            try {
                $user = $creator->create();
                (new Gazelle\Util\Mail)->send($user->email(), 'New account confirmation at '.SITE_NAME,
                    $Twig->render('email/registration.twig', [
                        'username'     => $username,
                        'announce_key' => $user->announceKey(),
                    ])
                );
                (new Gazelle\Manager\User)->sendPM( $user->id(), 0,
                    "Welcome to " . SITE_NAME,
                    $Twig->render('register/welcome.twig', [
                        'username'     => $username,
                        'announce_url' => $user->announceUrl(),
                    ])
                );
                (new Gazelle\Tracker)->update_tracker('add_user', ['id' => $user->id(), 'passkey' => $user->announceKey()]);
                $emailSent = true;
            }
            catch (Gazelle\Exception\UserCreatorException $e) {
                switch ($e->getMessage()) {
                    case 'email':
                        $error = 'No email address given';
                        break;
                    case 'ipaddr':
                        $error = 'No IP address given';
                        break;
                    case 'invitation':
                        echo $Twig->render('register/no-invite.twig');
                        exit;
                        break;
                    case 'password':
                        $error = 'No password given';
                        break;
                    case 'username':
                        $error = 'No username given';
                        break;
                    case 'username-invalid':
                        $error = 'Specified username is forbidden';
                        break;
                }
            }
            if (!$error) {
                $newInstall = $creator->newInstall();
            }
        }
    }
    echo $Twig->render('register/create.twig', [
        'error'     => $error ?? false,
        'js'        => $validator->generateJS('registerform'),
        'sent'      => $emailSent ?? false,
        'invite'    => $_REQUEST['invite'] ?? null,
        'is_new'    => $newInstall ?? false,
        'username'  => $_REQUEST['username'] ?? '',
        'email'     => $_REQUEST['email'] ?? $InviteEmail,
        'readrules' => $_REQUEST['readrules'] ?? false,
        'readwiki'  => $_REQUEST['readwiki'] ?? false,
        'agereq'    => $_REQUEST['agereq'] ?? false,
    ]);

} elseif (!OPEN_REGISTRATION) {
    echo $Twig->render(isset($_GET['welcome'])
        ? 'register/code.twig'
        : 'register/closed.twig'
    );
}
