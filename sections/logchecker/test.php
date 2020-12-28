<?php

use OrpheusNET\Logchecker\Logchecker;

View::show_header('Logchecker');
echo G::$Twig->render('logchecker/test.twig', [
    'accepted' => Logchecker::getAcceptValues(),
]);
View::show_footer();
