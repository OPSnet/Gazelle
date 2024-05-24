<?php

$classList = (new Gazelle\Manager\User())->classList();

echo $Twig->render('staffpm/user-inbox.twig', [
    'level' => [
        'fmod'  => $classList[FORUM_MOD]['Level'],
        'mod'   => $classList[MOD]['Level'],
        'sysop' => $classList[SYSOP]['Level'],
    ],
    'list'   => (new Gazelle\Manager\StaffPM())->findAllByUser($Viewer),
    'max'    => 'Sysop',
    'reply'  => new Gazelle\Util\Textarea('quickpost', ''),
    'viewer' => $Viewer,
]);
