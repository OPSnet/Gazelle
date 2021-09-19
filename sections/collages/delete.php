<?php

$collageId = (int)$_GET['collageid'];
if (!$collageId) {
    error(404);
}
$collage = new Gazelle\Collage($collageId);

if ($collage->isDeleted() && !$collage->isOwner($Viewer->id()) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

echo $Twig->render('collage/delete.twig', [
    'auth'        => $Viewer->auth(),
    'id'          => $collage->id(),
    'is_personal' => $collage->isPersonal(),
]);
