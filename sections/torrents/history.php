<?php

$torMan = new Gazelle\Manager\Torrent;
$group = $torMan->findGroupById((int)$_GET['groupid']);
if (is_null($group)) {
    error(404);
}

View::show_header($group->name() . " &rsaquo; Revision History");
echo $Twig->render('revision.twig', [
    'id'   => $group->id(),
    'list' => $group->revisionList(),
    'name' => $group->name(),
    'url'  => "torrents.php?id=",
]);
View::show_footer();
