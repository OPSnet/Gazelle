<?php

$collage = (new Gazelle\Manager\Collage)->findById((int)($_GET['collageid'] ?? $_GET['id'] ?? 0));
if (is_null($collage) || $collage->isArtist()) {
    error(404);
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

echo $Twig->render('collage/manage-tgroup.twig', [
    'collage'  => $collage,
    'manager'  => new Gazelle\Manager\TGroup,
    'viewer'   => $Viewer,
]);
