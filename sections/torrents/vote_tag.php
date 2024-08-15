<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();
$tgroup = (new Gazelle\Manager\TGroup())->findById((int)$_GET['groupid']);
$tag    = (new Gazelle\Manager\Tag())->findById((int)$_GET['tagid']);
$way    = $_GET['way'];

if (is_null($tgroup) || is_null($tag) || !in_array($way, ['up', 'down'])) {
    error(404);
}
if (!$tag->hasVoteTGroup($tgroup, $Viewer)) {
    $tag->voteTGroup($tgroup, $Viewer, $way);
}

header('Location: ' . redirectUrl($tgroup->location()));
