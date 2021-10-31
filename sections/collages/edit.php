<?php

$collage = (new Gazelle\Manager\Collage)->findById((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer->id()) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

echo $Twig->render('collage/edit.twig', [
    'can_rename'  => $Viewer->permitted('site_collages_delete')
        || ($collage->isPersonal() && $collage->isOwner($Viewer->id()) && $Viewer->permitted('site_collages_renamepersonal')),
    'collage'     => $collage,
    'description' => new Gazelle\Util\Textarea('description', $collage->description(), 60, 10),
    'error'       => $Err,
    'viewer'      => $Viewer,
]);
