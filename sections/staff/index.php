<?php

enforce_login();

$manager = new Gazelle\Manager\StaffPM;
$classList = (new \Gazelle\Manager\User)->classList();

View::show_header('Staff');
echo $Twig->render('staff/index.twig', [
    'hidden'=> true,
    'reply' => new Gazelle\Util\Textarea('message', ''),
    'fls'   => $manager->flsList(),
    'staff' => $manager->staffList(),
    'user'  => new Gazelle\User($LoggedUser['ID']),
    'level' => [
        'fmod'  => $classList[FORUM_MOD]['Level'],
        'mod'   => $classList[MOD]['Level'],
        'sysop' => $classList[SYSOP]['Level'],
    ],
]);
View::show_footer();
