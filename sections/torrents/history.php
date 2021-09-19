<?php

$group = (new Gazelle\Manager\TGroup)->findById((int)($_GET['groupid'] ?? 0));
if (is_null($group)) {
    error(404);
}

echo $Twig->render('revision.twig', [
    'id'   => $group->id(),
    'list' => $group->revisionList(),
    'name' => $group->name(),
    'url'  => "torrents.php?id=",
]);
