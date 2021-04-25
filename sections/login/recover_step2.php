<?php

$user = (new Gazelle\Manager\User)->findByResetKey($_GET['key']);
if (is_null($user)) {
    header('Location: login.php?action=recover');
    exit;
}
if ($user->resetPasswordExpired()) {
    $user->clearPasswordReset();
    header('Location: login.php?action=recover&expired=1');
    exit;
}

$validator = new Gazelle\Util\Validator;
$validator->setFields([
    ['verifypassword', '1', 'compare', 'Your passwords did not match.', ['comparefield' => 'password']],
    ['password', '1', 'regex',
        'You entered an invalid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol, or is 20 characters or longer',
        ['regex' => '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']
    ],
]);

$success = false;
if (!empty($_REQUEST['password'])) {
    if (!$validator->validate($_REQUEST)) {
        $error = $Validate->errorMessage();
    } else {
        // Form validates without error, set new secret and password.
        $user->clearPasswordReset();
        $user->updatePassword($_REQUEST['password'], $_SERVER['REMOTE_ADDR']);
        $user->logoutEverywhere();
        $success = true;
    }
}
View::show_header('Recover Password');

echo $Twig->render('login/new-password.twig', [
    'error'     => $error,
    'key'       => $_GET['key'],
    'success'   => $success,
    'validator' => $validator,
]);

View::show_footer(['recover' => true]);
