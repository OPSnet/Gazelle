<?php
/** @phpstan-var \Twig\Environment $Twig */

use OrpheusNET\Logchecker\Logchecker;

echo $Twig->render('logchecker/test.twig', [
    'accepted' => Logchecker::getAcceptValues(),
]);
