<?php

$auto = (FEATURE_EMAIL_REENABLE && isset($_POST['email']) && $_POST['email'] != '');

View::show_header('Disabled');
echo $Twig->render('login/disabled.twig', [
    'username' => $_COOKIE['username'],
    'auto'     => $auto,
    'message'  => $auto
        ? AutoEnable::new_request($_POST['username'], $_POST['email'])
        : "Please enter a valid email address.",
]);
View::show_footer();
