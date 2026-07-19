<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$collage = new Manager\Collage()->findById((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    Error404::error();
}
if ($collage->isDeleted() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    Error403::error();
}

echo $Twig->render('collage/delete.twig', [
    'collage' => $collage,
    'viewer'  => $Viewer,
]);
