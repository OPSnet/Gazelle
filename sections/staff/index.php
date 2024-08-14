<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$userMan = new \Gazelle\Manager\User();
$classList = $userMan->classList();

echo $Twig->render('staff/index.twig', [
    'hidden' => true,
    'reply' => new Gazelle\Util\Textarea('quickpost', ''),
    'fls'   => $userMan->flsList(),
    'staff' => $userMan->staffListGrouped(),
    'user'  => $Viewer,
    'level' => [
        'fmod'  => $classList[FORUM_MOD]['Level'],
        'mod'   => $classList[MOD]['Level'],
        'sysop' => $classList[SYSOP]['Level'],
    ],
]);
