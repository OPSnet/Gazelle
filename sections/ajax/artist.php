<?php

$artistMan = new Gazelle\Manager\Artist;
$revisionId = (int)($_GET['revisionid'] ?? 0);

if (isset($_GET['id'])) {
    if (isset($_GET['artistname'])) {
        json_die("failure", "cannot set both id and artistname");
    }
    $artist = $artistMan->findById((int)$_GET['id'], $revisionId);
    if (!$artist) {
        json_die("failure", "bad id");
    }
} elseif (isset($_GET['artistname'])) {
    $artist = $artistMan->findByName($_GET['artistname'], $revisionId);
    if (is_null($artist)) {
        json_die("failure", "bad artistname");
    }
} else {
    json_die("failure", "bad parameters");
}

$json = new Gazelle\Json\Artist($artist);
$json->setViewerId(new Gazelle\User($LoggedUser['ID']))
    ->setReleasesOnly(!empty($_GET['artistreleases']))
    ->setVersion(2)
    ->emit();
