<?php

$artist = (new Gazelle\Manager\Artist())->findById((int)($_GET['artistid'] ?? 0));
if (is_null($artist)) {
    error(404);
}

echo $Twig->render('artist/request-edit.twig', [
    'artist'  => $artist,
    'details' => new Gazelle\Util\Textarea('edit_details', '', 80, 10),
    'viewer'  => $Viewer,
]);
