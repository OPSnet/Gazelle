<?php

if (!defined('AJAX')) {
    authorize();
}

$artistMan = new Gazelle\Manager\Artist;
$artist = $artistMan->findById((int)($_POST['artistid'] ?? 0), 0);
if (is_null($artist)) {
    if (defined('AJAX')) {
        json_die('failure', 'no such artist');
    } else {
        error(404);
    }
}
$similar = $artistMan->findByName(trim($_POST['artistname'] ?? ''), 0);
if (is_null($similar)) {
    $similar = $artistMan->findById((int)($_POST['similarid'] ?? 0), 0);
    if (is_null($similar)) {
        if (defined('AJAX')) {
            json_die('failure', 'no such similar artist name');
        } else {
            error('Unknown similar artist name.');
        }
    }
}
$artist->addSimilar($similar, $Viewer->id());

if (defined('AJAX')) {
    json_print('success', [
        'artist' => $artist->id(),
        'similar' => $similar->id(),
    ]);
} else {
    header("Location: " . redirectUrl("artist.php?id=" . $artist->id()));
}
