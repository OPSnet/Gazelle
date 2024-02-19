<?php

authorize();

$tgMan = new Gazelle\Manager\TGroup();
$tgroup = $tgMan->findById((int)$_POST['groupid']);
if (is_null($tgroup)) {
    error(404);
}

$tgroup->addArtists($_POST['importance'], $_POST['aliasname'], $Viewer, new Gazelle\Manager\Artist(), new Gazelle\Log());

header('Location: ' . redirectUrl($tgroup->location()));
