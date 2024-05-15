<?php

use Gazelle\Enum\CollageType;

if (!$Viewer->permitted('site_collages_create') && !$Viewer->canCreatePersonalCollage()) {
    error(403);
}

// the variables below are instantiated via new_handle.php in the event of an error

echo $Twig->render('collage/new.twig', [
    'category'    => $Category ?? false,
    'description' => new Gazelle\Util\Textarea('description', $Description ?? '', 60, 10),
    'error'       => $Err ?? false,
    'name'        => $Name ?? '',
    'no_name'     => !$Viewer->permitted('site_collages_renamepersonal') && (!$Viewer->permitted('site_collages_create') || ($Category ?? -1) === CollageType::personal->value),
    'tags'        => $Tags ?? '',
    'viewer'      => $Viewer,
]);
