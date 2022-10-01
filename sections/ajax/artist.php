<?php

$artistMan = new Gazelle\Manager\Artist;
$revisionId = isset($_GET['revisionid']) ? (int)$_GET['revisionid'] : null;

if (isset($_GET['id'])) {
    if (isset($_GET['artistname'])) {
        json_die("failure", "cannot set both id and artistname");
    }
    if (is_null($revisionId)) {
        $artist = $artistMan->findById((int)$_GET['id']);
        if (is_null($artist)) {
            json_die("failure", "bad id");
        }
    } else {
        $artist = $artistMan->findByIdAndRevision((int)$_GET['id'], $revisionId);
        if (is_null($artist)) {
            json_die("failure", "bad id or revision");
        }
    }
} elseif (isset($_GET['artistname'])) {
    if (is_null($revisionId)) {
        $artist = $artistMan->findByName($_GET['artistname']);
        if (is_null($artist)) {
            json_die("failure", "bad artistname");
        }
    } else {
        $artist = $artistMan->findByNameAndRevision($_GET['artistname'], $revisionId);
        if (is_null($artist)) {
            json_die("failure", "bad artistname or revision");
        }
    }
} else {
    json_die("failure", "bad parameters");
}

(new Gazelle\Json\Artist($artist, $Viewer, new Gazelle\Manager\TGroup, new Gazelle\Manager\Torrent))
    ->setReleasesOnly(!empty($_GET['artistreleases']))
    ->setVersion(2)
    ->emit();
