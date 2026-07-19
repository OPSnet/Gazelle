<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_collages_manage')) {
    Error403::error();
}

$collage = new Manager\Collage()->findById((int)($_GET['collageid'] ?? $_GET['id'] ?? 0));
if (is_null($collage) || $collage->isArtist()) {
    Error404::error();
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    Error403::error();
}

echo $Twig->render('collage/manage-tgroup.twig', [
    'collage' => $collage,
    'list'    => object_generator(new Manager\TGroup(), $collage->groupIds()),
    'viewer'  => $Viewer,
]);
