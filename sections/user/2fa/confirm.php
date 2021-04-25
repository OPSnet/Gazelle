<?php

View::show_header('Disable Two-factor Authentication');
echo $Twig->render('user/2fa/remove.twig', [
    'bad' => isset($_GET['invalid']),
]);
View::show_footer();
