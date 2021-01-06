<?php

// User wants to reset their password

$Validate = new Gazelle\Util\Validator;
$sent = 0;
if (!empty($_REQUEST['email'])) {
    // User has entered email and submitted form
    $Validate->setField('email', '1', 'email', 'You entered an invalid email address.');
    $error = $Validate->validate($_REQUEST) ? false : $Validate->errorMessage();
    if (!$error) {
        $user = (new Gazelle\Manager\User)->findByEmail(trim($_REQUEST['email']));
        if ($user) {
            $user->resetPassword();
            $session = (new Gazelle\Session($user->id()))->dropAll();
            $session->dropAll();
            $sent = 1; // If $sent is 1, recover_step1.php displays a success message
        }
        $error = "Email sent with further instructions.";
    }
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['reseterr'])) {
        // User has not entered email address, and there is an error set in session data
        // This is typically because their key has expired.
        $error = $_SESSION['reseterr'];
        unset($_SESSION['reseterr']);
    }
    session_write_close();
}

// Either a form for the user's email address, or a success message
View::show_header('Recover Password','validate');
echo $Validate->generateJS('recoverform');
echo G::$Twig('login/reset-password.twig', [
    'error' => $error,
    'sent'  => $sent,
]);
View::show_footer(['recover' => true]);
