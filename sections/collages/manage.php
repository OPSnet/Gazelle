<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_collages_manage')) {
    error(403);
}

$collage = (new Gazelle\Manager\Collage())->findById((int)($_GET['collageid'] ?? $_GET['id'] ?? 0));
if (is_null($collage) || $collage->isArtist()) {
    error(404);
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

echo $Twig->render('collage/manage-tgroup.twig', [
    'collage' => $collage,
    'list'    => object_generator(new Gazelle\Manager\TGroup(), $collage->groupIds()),
    'viewer'  => $Viewer,
]);
