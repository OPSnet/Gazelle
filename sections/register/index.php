<?php
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\UserCreatorError;
use Gazelle\Enum\UserStatus;
use Gazelle\Enum\UserTokenType;

if (isset($_REQUEST['confirm'])) {
    // Confirm registration
    $token = new Manager\UserToken()->findByToken($_REQUEST['confirm']);
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
    new Tracker()->addUser($user);
} elseif (OPEN_REGISTRATION || isset($_REQUEST['invite']) || new Stats\Users()->enabledUserTotal() == 0) {
    if ($_REQUEST['invite']) {
        if (!new Manager\Invite()->inviteExists($_REQUEST['invite'])) {
            echo $Twig->render('register/no-invite.twig');
            exit;
        }
    }

    $validator = new Util\Validator();
    $validator->setFields([
        ['username', true, 'regex', 'You did not enter a valid username.', ['regex' => USERNAME_REGEXP]],
        ['email', true, 'email', 'You did not enter a valid email address.'],
        ['password', true, 'regex', 'A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer', ['regex' => Util\PasswordCheck::REGEXP]],
        ['confirm_password', true, 'compare', 'Your passwords do not match.', ['comparefield' => 'password']],
        ['readrules', true, 'checkbox', 'You did not check the box that says you will read the rules.'],
        ['readwiki',  true, 'checkbox', 'You did not check the box that says you will read the wiki.'],
        ['agereq',    true, 'checkbox', 'You did not check the box that says you are 13 years of age or older.'],
        ['vpn',       true, 'checkbox', 'You did not check the box that says you are not connected via a VPN.'],
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

            if (!Util\PasswordCheck::checkPasswordStrengthNoUser($_POST['password'], $username, $email)) {
                $error = Util\PasswordCheck::ERROR_MSG;
                break;
            }

            $creator = new UserCreator();
            $creator->setUsername($username)
                ->setEmail($email)
                ->setPassword($_POST['password']);
            if ($_REQUEST['invite']) {
                $creator->setInviteKey($_REQUEST['invite']);
            }

            try {
                $newInstall = $creator->newInstall();
                $user = $creator->create();
                if ($newInstall) {
                    header("Location: /login.php");
                    exit;
                }
                new Util\Mail()->send($user->email(), 'New account confirmation at ' . SITE_NAME,
                    $Twig->render('email/registration.text.twig', [
                        'user'   => $user,
                        'token'  => new Manager\UserToken()->create(UserTokenType::confirm, $user),
                    ])
                );
                $emailSent = true;
            } catch (Exception\UserCreatorException $e) {
                if ($e->error === UserCreatorError::invitation) {
                    echo $Twig->render('register/no-invite.twig');
                    exit;
                }
                $error = match ($e->error) {
                    UserCreatorError::duplicate => 'Someone already took that username :-(',
                    UserCreatorError::email     => 'The specified email is forbidden.',
                    UserCreatorError::password  => 'The specified password is forbidden.',
                    UserCreatorError::username  => 'The specified username contains illegal characters.',
                };
            }
            if (!$error) {
                $newInstall = $creator->newInstall();
            }
            break;  // never loop
        }
    }
    echo $Twig->render('register/create.twig', [
        'error'     => $error,
        'sent'      => $emailSent ?? false,
        'invite'    => $_REQUEST['invite'] ?? null,
        'is_new'    => $newInstall ?? false,
        'username'  => $_REQUEST['username'] ?? '',
        'email'     => $_REQUEST['email'] ?? '',
        'readrules' => isset($_REQUEST['readrules']),
        'readwiki'  => isset($_REQUEST['readwiki']),
        'agereq'    => isset($_REQUEST['agereq']),
        'vpn'       => isset($_REQUEST['vpn']),
    ]);
} else {
    echo $Twig->render(
        isset($_GET['welcome']) ? 'register/code.twig' : 'register/closed.twig'
    );
}
