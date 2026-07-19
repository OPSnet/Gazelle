<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();
$tgroup = new Manager\TGroup()->findById((int)$_GET['groupid']);
$tag    = new Manager\Tag()->findById((int)$_GET['tagid']);
$way    = $_GET['way'];

if (is_null($tgroup) || is_null($tag) || !in_array($way, ['up', 'down'])) {
    Error404::error();
}
if (!$tag->hasVoteTGroup($tgroup, $Viewer)) {
    $tag->voteTGroup($tgroup, $Viewer, $way);
}

header('Location: ' . redirectUrl($tgroup->location()));
