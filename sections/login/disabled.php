<?php

$auto = (FEATURE_EMAIL_REENABLE && isset($_POST['email']) && $_POST['email'] != '');

echo $Twig->render('login/disabled.twig', [
    'username' => $_COOKIE['username'],
    'auto'     => $auto,
    'message'  => $auto
        ? AutoEnable::new_request($_POST['username'], $_POST['email'])
        : "Please enter a valid email address.",
]);
