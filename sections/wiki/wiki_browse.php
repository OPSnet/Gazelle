<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$letter = isset($_GET['letter']) ? strtoupper(substr($_GET['letter'], 0, 1)) : '1';

echo $Twig->render('wiki/browse.twig', [
    'articles' => new Manager\Wiki()->articles($Viewer->privilege()->effectiveClassLevel(), $letter),
    'letter'   => $letter,
]);
