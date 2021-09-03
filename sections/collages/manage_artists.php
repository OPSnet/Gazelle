<?php

if (!$Viewer->permitted('site_collages_create')) {
    error(403);
}
$collage = (new Gazelle\Manager\Collage)->findById((int)$_GET['collageid']);
if (is_null($collage)) {
    error(404);
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer->id()) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}
if (!$collage->isArtist()) {
    error(404);
}

View::show_header("Manage artist collage " . $collage->name(), ['js' => 'jquery-ui,jquery.tablesorter,sort']);
echo $Twig->render('collage/manage-artists.twig', [
    'collage' => $collage,
    'viewer'  => $Viewer,
]);
View::show_footer();
