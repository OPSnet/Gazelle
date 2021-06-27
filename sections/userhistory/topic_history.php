<?php

if ($Viewer->disableForums()) {
    error(403);
}

$user = empty($_GET['userid']) ? $Viewer : (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}
$forumSearch = new Gazelle\ForumSearch($user);
if ($Viewer->id() != $user->id()) {
    $forumSearch->setViewer($Viewer);
}

$paginator = new Gazelle\Util\Paginator(TOPICS_PER_PAGE, (int)($_REQUEST['page'] ?? 1));
$paginator->setTotal($forumSearch->threadsByUserTotal());

View::show_header($user->username() . " &rsaquo; Threads created", 'subscriptions,comments,bbcode');
echo $Twig->render('user/thread-history.twig', [
    'paginator' => $paginator,
    'page' => $forumSearch->threadsByUserPage($paginator->limit(), $paginator->offset()),
    'user' => $user,
]);
View::show_footer();
