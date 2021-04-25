<?php

use OrpheusNET\Logchecker\Logchecker;

View::show_header('Logchecker');
echo $Twig->render('logchecker/test.twig', [
    'accepted' => Logchecker::getAcceptValues(),
]);
View::show_footer();
