<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error();
}

$artist = new Manager\Artist()->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    $id = html_escape($_GET['artistid']); // might not be a number
    Error400::error(
        "Cannot find an artist with the ID $id: See the <a href=\"log.php?search=Artist+$id\">site log</a>."
    );
}

echo $Twig->render('artist/edit.twig', [
    'artist' => $artist,
    'body'   => new Util\Textarea('body', $artist->body() ?? '', 91, 15),
    'viewer' => $Viewer,
]);
