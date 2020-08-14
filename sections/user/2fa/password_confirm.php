<?php
View::show_header('Disable Two-factor Authentication');

echo G::$Twig->render('login/2fa-remove.twig', [
    'bad' => isset($_GET['invalid']),
]);

View::show_footer();

