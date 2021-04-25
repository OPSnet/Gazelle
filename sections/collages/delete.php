<?php

$collageId = (int)$_GET['collageid'];
if (!$collageId) {
    error(404);
}
$collage = new Gazelle\Collage($collageId);

if ($collage->isDeleted() && !$collage->isOwner($LoggedUser['ID']) && !check_perms('site_collages_delete')) {
    error(403);
}

View::show_header('Delete collage');
echo $Twig->render('collage/delete.twig', [
    'auth'        => $LoggedUser['AuthKey'],
    'id'          => $collage->id(),
    'is_personal' => $collage->isPersonal(),
]);
View::show_footer();
