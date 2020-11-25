<?php

if (!empty($LoggedUser['DisableForums'])) {
    json_error('You do not have access to the forums!');
}

$user = (new Gazelle\Manager\User)->findById(empty($_GET['userid']) ? $LoggedUser['ID'] : (int)$_GET['userid']);
if (!$user) {
    json_error('User does not exist!');
}
$ownProfile = ($user->id() === $LoggedUser['ID']);

$forumSearch = (new Gazelle\ForumSearch($user))
    ->setShowGrouped($ownProfile && (!isset($_GET['group']) || !!$_GET['group']))
    ->setShowUnread($ownProfile && (!isset($_GET['showunread']) || !!$_GET['showunread']));
$paginator = new Gazelle\Util\Paginator($LoggedUser['PostsPerPage'] ?? POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));

$json = new Gazelle\Json\PostHistory;
$json->setForumSearch($forumSearch)
    ->setPaginator($paginator)
    ->emit();
