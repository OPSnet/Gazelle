<?php

/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$group = new Manager\TGroup()->findById((int)($_GET['id'] ?? 0));
if (is_null($group)) {
    Error404::error();
}

echo $Twig->render('revision.twig', ['object' => $group]);
