<?php

$artistMan  = new Gazelle\Manager\Artist();
$revisionId = isset($_GET['revisionid']) ? (int)$_GET['revisionid'] : null;
$artistId   = (int)($_GET['id'] ?? 0);

if ($artistId) {
    if (isset($_GET['artistname'])) {
        json_die("failure", "cannot set both id and artistname");
    }
    if (is_null($revisionId)) {
        $artist = $artistMan->findById($artistId);
        if (is_null($artist)) {
            json_die("failure", "bad id");
        }
    } else {
        $artist = $artistMan->findByIdAndRevision($artistId, $revisionId);
        if (is_null($artist)) {
            json_die("failure", "bad id or revision");
        }
    }
} elseif (isset($_GET['artistname'])) {
    $artistName = trim($_GET['artistname']);
    if (is_null($revisionId)) {
        $artist = $artistMan->findByName($artistName);
        if (is_null($artist)) {
            json_die("failure", "bad artistname");
        }
    } else {
        $artist = $artistMan->findByNameAndRevision($artistName, $revisionId);
        if (is_null($artist)) {
            json_die("failure", "bad artistname or revision");
        }
    }
} else {
    json_die("failure", "bad parameters");
}

echo (new Gazelle\Json\Artist(
    $artist,
    $Viewer,
    new Gazelle\User\Bookmark($Viewer),
    new Gazelle\Manager\Request(),
    new Gazelle\Manager\TGroup(),
    new Gazelle\Manager\Torrent(),
))
    ->setReleasesOnly(!empty($_GET['artistreleases']))
    ->setVersion(2)
    ->response();
