<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$classList = new Manager\User()->classList();

echo $Twig->render('staffpm/user-inbox.twig', [
    'level' => [
        'fmod'  => $classList[FORUM_MOD]['Level'],
        'mod'   => $classList[MOD]['Level'],
        'sysop' => $classList[SYSOP]['Level'],
    ],
    'list'   => new Manager\StaffPM()->findAllByUser($Viewer),
    'max'    => 'Sysop',
    'reply'  => new Util\Textarea('quickpost', ''),
    'viewer' => $Viewer,
]);
