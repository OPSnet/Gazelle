<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$artist = (new Gazelle\Manager\Artist())->findById((int)$_GET['artistid']);
if (is_null($artist)) {
    $id = html_escape($_GET['artistid']); // might not be a number
    error("Cannot find an artist with the ID $id: See the <a href=\"log.php?search=Artist+$id\">site log</a>.");
}

echo $Twig->render('artist/edit.twig', [
    'artist' => $artist,
    'body'   => new Gazelle\Util\Textarea('body', $artist->body() ?? '', 91, 15),
    'viewer' => $Viewer,
]);
