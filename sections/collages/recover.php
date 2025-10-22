<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_collages_recover')) {
    Error403::error("You are not allowed to recover collages");
}

$id   = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if ($id || $name !== '') {
    authorize();
    $collage = null;
    if ($id) {
        $collage = new Manager\Collage()->recoverById($id);
    }
    if (!$collage && $name !== '') {
        $collage = new Manager\Collage()->recoverByName($name);
    }
    if (!$collage) {
        Error404::error('Collage is completely deleted');
    } else {
        $collage->logger()->general("Collage {$collage->flush()->id} was recovered by {$Viewer->username()}");
        header('Location: ' . $collage->location());
        exit;
    }
}

echo $Twig->render('collage/recover.twig', [
    'viewer' => $Viewer,
]);
