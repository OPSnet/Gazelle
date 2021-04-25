<?php

$user = (new Gazelle\Manager\User)->findById((int)($_REQUEST['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start(['read_and_close' => true]);
}
if (empty($_SESSION['private_key'])) {
    error(404);
}

$user->create2FA($_SESSION['private_key']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['private_key']);
session_write_close();

View::show_header('Two-factor Authentication');
echo $Twig->render('user/2fa/complete.twig', [
    'keys' => $user->list2FA(),
]);
View::show_footer();
