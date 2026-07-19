<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_collages_create')) {
    Error403::error();
}
$collage = new Manager\Collage()->findById((int)$_GET['collageid']);
if (is_null($collage)) {
    Error404::error();
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    Error403::error();
}
if (!$collage->isArtist()) {
    Error404::error();
}

echo $Twig->render('collage/manage-artists.twig', [
    'collage' => $collage,
    'viewer'  => $Viewer,
]);
