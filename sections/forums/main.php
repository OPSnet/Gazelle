<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

echo $Twig->render('forum/main.twig', [
    'toc'    => new Manager\Forum()->tableOfContents($Viewer),
    'viewer' => $Viewer,
]);
