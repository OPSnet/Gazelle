<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('site_edit_wiki')) {
    error(403);
}

$collage = (new Gazelle\Manager\Collage())->findById((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}
$torMan = new Gazelle\Manager\Torrent();

echo $Twig->render('collage/edit.twig', [
    'can_rename'   => $Viewer->permitted('site_collages_delete')
        || ($collage->isPersonal() && $collage->isOwner($Viewer) && $Viewer->permitted('site_collages_renamepersonal')),
    'collage'      => $collage,
    'description'  => new Gazelle\Util\Textarea('description', $collage->description(), 60, 10),
    'error'        => $Err ?? false,
    'leech_type'   => $torMan->leechTypeList(),
    'leech_reason' => $torMan->leechReasonList(),
    'viewer'       => $Viewer,
]);
