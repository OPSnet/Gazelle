<?php

authorize();
$tgroup = (new Gazelle\Manager\TGroup)->findById((int)$_GET['groupid']);
$tagId  = (int)$_GET['tagid'];
$way    = $_GET['way'];

if (is_null($tgroup) || !$tagId || !in_array($way, ['up', 'down'])) {
    error(404);
}
$tgroup->addTagVote($Viewer->id(), $tagId, $way);

header("Location: " . redirectUrl("torrents.php?id=" . $tgroup->id()));
