<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

echo $Twig->render('forum/main.twig', [
    'toc'    => (new Gazelle\Manager\Forum())->tableOfContents($Viewer),
    'viewer' => $Viewer,
]);
