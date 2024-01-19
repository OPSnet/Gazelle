<?php

$collageId = (int)$_GET['collageid'];
if (!$collageId) {
    error(404);
}
$collage = new Gazelle\Collage($collageId);

if ($collage->isDeleted() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

echo $Twig->render('collage/delete.twig', [
    'collage' => $collage,
    'viewer'  => $Viewer,
]);
