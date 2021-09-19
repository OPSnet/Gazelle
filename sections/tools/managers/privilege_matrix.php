<?php

$privMan = new Gazelle\Manager\Privilege;

$Twig->render('admin/privilege-matrix.twig', [
    'class_list' => $privMan->classList(),
    'privilege'  => $privMan->privilege(),
    'star'       => "\xE2\x98\x85",
    'tick'       => ICON_ALL,
]);
