<?php

use Gazelle\Enum\UserTokenType;

$userToken = (new Gazelle\Manager\UserToken)->findByToken($_GET['key']);
if ($userToken?->type() != UserTokenType::password) {
    header('Location: login.php?action=recover');
    exit;
}

$validator = new Gazelle\Util\Validator;
$validator->setFields([
    ['verifypassword', true, 'compare', 'Your passwords did not match.', ['comparefield' => 'password']],
    ['password', true, 'regex',
        'You entered an invalid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer',
        ['regex' => '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']
    ],
]);

$error   = false;
$success = false;
if (!empty($_REQUEST['password'])) {
    if (!$validator->validate($_REQUEST)) {
        $error = $validator->errorMessage();
    } else {
        // Form validates without error, try and use the token
        if (!$userToken->consume()) {
            header('Location: login.php?action=recover&expired=1');
            exit;
        } else {
            // set new secret and password.
            $userToken->user()->updatePassword($_REQUEST['password'], $_SERVER['REMOTE_ADDR']);
            $userToken->user()->logoutEverywhere();
            $success = true;
        }
    }
}

echo $Twig->render('login/new-password.twig', [
    'error'     => $error,
    'key'       => $_GET['key'],
    'success'   => $success,
    'validator' => $validator,
]);
