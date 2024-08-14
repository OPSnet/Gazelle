<?php
/** @phpstan-var ?\Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (isset($Viewer)) {
    header("Location: index.php");
    exit;
}

echo $Twig->render('recovery/index.twig');
