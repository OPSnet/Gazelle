<?php

/**
 * @phpstan-var ?\Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (isset($Viewer)) {
    header("Location: index.php");
    exit;
}

echo $Twig->render('recovery/index.twig');
