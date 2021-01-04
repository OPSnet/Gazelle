<?php
View::show_header('Two-factor Authentication');

$UserID = (int)$_REQUEST['userid'];
if (!$UserID) {
    error(404);
}

$keys = unserialize($DB->scalar("
    SELECT Recovery FROM users_main WHERE ID = ?
    ", $UserID
));

echo G::$Twig->render('login/2fa-backup.twig', [
    'keys' => $keys,
]);

View::show_footer();
