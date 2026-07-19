<?php

/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!SHOW_PUBLIC_INDEX) {
    header('Location: login.php');
    exit;
}
echo $Twig->render('index/public.twig', [
    'new' => new Stats\Users()->enabledUserTotal() == 0,
]);
