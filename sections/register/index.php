<?php

use Gazelle\Enum\UserStatus;
use Gazelle\Enum\UserTokenType;

if (isset($_REQUEST['confirm'])) {
    // Confirm registration
    $token = (new Gazelle\Manager\UserToken())->findByToken($_REQUEST['confirm']);
    if (!$token || $token->type() != UserTokenType::confirm || !$token->consume()) {
        // we have no token, or not of the right type, or not consumable (expired)
        echo $Twig->render('register/expired.twig');
        exit;
    }
    $user = $token->user();
    $user->setField('Enabled', UserStatus::enabled->value)->modify();
    $user->inbox()->createSystem(
        "Welcome to " . SITE_NAME,
        $Twig->render('register/welcome.bbcode.twig', ['user' => $user])
    );
    echo $Twig->render('register/complete.twig');
    (new Gazelle\Tracker())->addUser($user);
} elseif (OPEN_REGISTRATION || isset($_REQUEST['invite']) || (new Gazelle\Stats\Users())->enabledUserTotal() == 0) {
    if ($_REQUEST['invite']) {
        if (!(new Gazelle\Manager\Invite())->inviteExists($_GET['invite'])) {
            echo $Twig->render('register/no-invite.twig');
            exit;
        }
    }

    $validator = new Gazelle\Util\Validator();
    $validator->setFields([
        ['username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEXP]],
        ['email', true, 'email', 'You did not enter a valid email address.'],
        ['password', true, 'regex', 'A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer', ['regex' => \Gazelle\Util\PasswordCheck::REGEXP]],
        ['confirm_password', true, 'compare', 'Your passwords do not match.', ['comparefield' => 'password']],
        ['readrules', true, 'checkbox', 'You did not select the box that says you will read the rules.'],
        ['readwiki', true, 'checkbox', 'You did not select the box that says you will read the wiki.'],
        ['agereq', true, 'checkbox', 'You did not select the box that says you are 13 years of age or older.'],
    ]);

    $error = false;
    if (isset($_POST['submit'])) {
        while (true) {
            if (!$validator->validate($_POST)) {
                $error = $validator->errorMessage();
                break;
            }

            $username = trim($_POST['username']);
            $email    = trim($_POST['email']);

            if (!\Gazelle\Util\PasswordCheck::checkPasswordStrengthNoUser($_POST['password'], $username, $email)) {
                $error = \Gazelle\Util\PasswordCheck::ERROR_MSG;
                break;
            }

            $creator = new Gazelle\UserCreator();
            $creator->setUsername($username)
                ->setEmail($email)
                ->setPassword($_POST['password'])
                ->setIpaddr($_SERVER['REMOTE_ADDR']);
            if ($_REQUEST['invite']) {
                $creator->setInviteKey($_REQUEST['invite']);
            }

            try {
                $user = $creator->create();
                (new Gazelle\Util\Mail())->send($user->email(), 'New account confirmation at ' . SITE_NAME,
                    $Twig->render('email/registration.twig', [
                        'ipaddr' => $_SERVER['REMOTE_ADDR'],
                        'user'   => $user,
                        'token'  => (new \Gazelle\Manager\UserToken())->create(UserTokenType::confirm, $user),
                    ])
                );
                $emailSent = true;
            } catch (Gazelle\Exception\UserCreatorException $e) {
                switch ($e->getMessage()) {
                    case 'duplicate':
                        $error = 'Someone already took that username :-(';
                        break;
                    case 'email':
                        $error = 'No email address given';
                        break;
                    case 'ipaddr':
                        $error = 'No IP address given';
                        break;
                    case 'invitation':
                        echo $Twig->render('register/no-invite.twig');
                        exit;
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
            break;  // never loop
        }
    }
    echo $Twig->render('register/create.twig', [
        'error'     => $error,
        'js'        => $validator->generateJS('registerform'),
        'sent'      => $emailSent ?? false,
        'invite'    => $_REQUEST['invite'] ?? null,
        'is_new'    => $newInstall ?? false,
        'username'  => $_REQUEST['username'] ?? '',
        'email'     => $_REQUEST['email'] ?? '',
        'readrules' => $_REQUEST['readrules'] ?? false,
        'readwiki'  => $_REQUEST['readwiki'] ?? false,
        'agereq'    => $_REQUEST['agereq'] ?? false,
    ]);
} else {
    echo $Twig->render(isset($_GET['welcome'])
        ? 'register/code.twig'
        : 'register/closed.twig'
    );
}
