<?php

if ($Viewer->disableForums()) {
    json_error('You do not have access to the forums!');
}

$user = empty($_GET['userid']) ? $Viewer : (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    json_error('User does not exist!');
}
$ownProfile = ($user->id() === $Viewer->id());

$forumSearch = (new Gazelle\Search\Forum($user))
    ->setViewer($Viewer)
    ->setShowGrouped($ownProfile && (!isset($_GET['group']) || !!$_GET['group']))
    ->setShowUnread($ownProfile && (!isset($_GET['showunread']) || !!$_GET['showunread']));

(new Gazelle\Json\PostHistory)
    ->setForumSearch($forumSearch)
    ->setPaginator(new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1)))
    ->emit();
