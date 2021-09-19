<?php

use OrpheusNET\Logchecker\Logchecker;

echo $Twig->render('logchecker/test.twig', [
    'accepted' => Logchecker::getAcceptValues(),
]);
