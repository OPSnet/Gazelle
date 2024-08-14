<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!defined('AJAX')) {
    authorize();
}

$artistMan = new Gazelle\Manager\Artist();
$artist = $artistMan->findById((int)($_POST['artistid'] ?? 0));
if (is_null($artist)) {
    if (defined('AJAX')) {
        json_die('failure', 'no such artist');
    } else {
        error(404);
    }
}
$other = $artistMan->findByName(trim($_POST['artistname'] ?? ''));
if (is_null($other)) {
    $other = $artistMan->findById((int)($_POST['similarid'] ?? 0));
    if (is_null($other)) {
        if (defined('AJAX')) {
            json_die('failure', 'no such similar artist name');
        } else {
            error('Unknown similar artist name.');
        }
    }
}
$artist->similar()->addSimilar($other, $Viewer, new Gazelle\Log());

if (defined('AJAX')) {
    json_print('success', [
        'artist'  => $artist->id(),
        'similar' => $artist->similar()->findSimilarId($other),
    ]);
} else {
    header("Location: {$artist->location()}");
}
