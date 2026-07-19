<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$artist = new Manager\Artist()->findById((int)($_GET['artistid'] ?? 0));
if (is_null($artist)) {
    Error404::error();
}

echo $Twig->render('artist/request-edit.twig', [
    'artist'  => $artist,
    'details' => new Util\Textarea('edit_details', '', 80, 10),
    'viewer'  => $Viewer,
]);
