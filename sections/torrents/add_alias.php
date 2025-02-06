<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$tgMan = new Gazelle\Manager\TGroup();
$tgroup = $tgMan->findById((int)$_POST['groupid']);
if (is_null($tgroup)) {
    error(404);
}

$count = $tgroup->addArtists(
    $_POST['importance'],
    $_POST['aliasname'],
    $Viewer,
    new Gazelle\Manager\Artist()
);

if ($count < 1) {
    error("artist already added");
}

header('Location: ' . redirectUrl($tgroup->location()));
