<?php

enforce_login();

$userMan = new \Gazelle\Manager\User;
$classList = $userMan->classList();

View::show_header('Staff');
echo $Twig->render('staff/index.twig', [
    'hidden'=> true,
    'reply' => new Gazelle\Util\Textarea('message', ''),
    'fls'   => $userMan->flsList(),
    'staff' => $userMan->staffListGrouped(),
    'user'  => new Gazelle\User($LoggedUser['ID']),
    'level' => [
        'fmod'  => $classList[FORUM_MOD]['Level'],
        'mod'   => $classList[MOD]['Level'],
        'sysop' => $classList[SYSOP]['Level'],
    ],
]);
View::show_footer();
