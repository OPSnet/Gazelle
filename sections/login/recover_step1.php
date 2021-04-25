<?php

$validator = new Gazelle\Util\Validator;
$validator->setField('email', '1', 'email', 'You entered an invalid email address.');

$sent = false;
if (isset($_REQUEST['expired'])) {
    $error = 'The link you followed has expired.';
} elseif (!empty($_REQUEST['email'])) {
    $error = $validator->validate($_REQUEST) ? false : $validator->errorMessage();
    if (!$error) {
        $user = (new Gazelle\Manager\User)->findByEmail(trim($_REQUEST['email']));
        if ($user) {
            $user->resetPassword($Twig);
            $user->logoutEverywhere();
            $sent = true;
        }
        $error = "Email sent with further instructions.";
    }
}

View::show_header('Recover Password','validate');
echo $validator->generateJS('recoverform');
echo $Twig->render('login/reset-password.twig', [
    'error' => $error,
    'sent'  => $sent,
]);
View::show_footer(['recover' => true]);
