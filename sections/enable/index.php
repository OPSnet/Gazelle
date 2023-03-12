<?php

if (!FEATURE_EMAIL_REENABLE) {
    header("Location: index.php");
    exit;
}
if (!isset($_GET['token'])) {
    header("Location: index.php");
    exit;
}

$enabler = (new Gazelle\Manager\AutoEnable)->findByToken($_GET['token']);
if (is_null($enabler)) {
    error('invalid enable token');
}

echo $Twig->render('enable/processed.twig', [
    'success' => $enabler->processToken(),
]);
