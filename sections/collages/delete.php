<?php

$collage = (new Gazelle\Manager\Collage())->findById((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}
if ($collage->isDeleted() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

echo $Twig->render('collage/delete.twig', [
    'collage' => $collage,
    'viewer'  => $Viewer,
]);
