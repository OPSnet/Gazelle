<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

echo $Twig->render('chat/index.twig', [
    'user' => $Viewer,
]);
