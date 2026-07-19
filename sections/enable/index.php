<?php

/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!FEATURE_EMAIL_REENABLE) {
    header("Location: index.php");
    exit;
}
if (!isset($_GET['token'])) {
    header("Location: index.php");
    exit;
}

$enabler = new Manager\AutoEnable()->findByToken($_GET['token']);
if (is_null($enabler)) {
    Error400::error('invalid enable token');
}

echo $Twig->render('enable/processed.twig', [
    'success' => $enabler->processToken(),
]);
