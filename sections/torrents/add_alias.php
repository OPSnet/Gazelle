<?php
authorize();

$tgMan = new Gazelle\Manager\TGroup;
$tgroup = $tgMan->findById((int)$_POST['groupid']);
if (is_null($tgroup)) {
    error(404);
}

if ($tgroup->addArtists($Viewer, $_POST['importance'], $_POST['aliasname'])) {
    $tgMan->refresh($tgroup->id());
}

header('Location: ' . redirectUrl($tgroup->location()));
