<?php

$forum = (new Gazelle\Manager\Forum())->findById((int)($_GET['forumid'] ?? 0));
if (is_null($forum)) {
    print json_die(['status' => $_GET]);
}
if (!$Viewer->readAccess($forum)) {
    json_die("failure", "insufficient permission");
}

echo (new Gazelle\Json\Forum(
    $forum,
    $Viewer,
    new Gazelle\Manager\ForumThread(),
    new Gazelle\Manager\User(),
    isset($_GET['pp']) ? (int)$_GET['pp'] : $Viewer->postsPerPage(),
    (int)($_GET['page'] ?? 1),
))
    ->setVersion(2)
    ->response();
