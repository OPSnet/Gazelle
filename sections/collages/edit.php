<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_edit_wiki')) {
    Error403::error();
}

$collage = new Manager\Collage()->findById((int)($_GET['collageid'] ?? 0));
if (is_null($collage)) {
    Error404::error();
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    Error403::error();
}
$torMan = new Manager\Torrent();

echo $Twig->render('collage/edit.twig', [
    'can_rename'   => $Viewer->permitted('site_collages_delete')
        || ($collage->isPersonal() && $collage->isOwner($Viewer) && $Viewer->permitted('site_collages_renamepersonal')),
    'collage'      => $collage,
    'description'  => new Util\Textarea('description', $collage->description(), 60, 10),
    'error'        => $Err ?? false,
    'leech_type'   => $torMan->leechTypeList(),
    'leech_reason' => $torMan->leechReasonList(),
    'viewer'       => $Viewer,
]);
