<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

echo $Twig->render('reportsv2/linkbox.twig', ['viewer' => $Viewer]);
