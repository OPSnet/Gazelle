<?php

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid'] ?: $LoggedUser['ID']);
if (is_null($user)) {
    error(404);
}
$forumSearch = new Gazelle\ForumSearch($user);
if ($LoggedUser['ID'] != $user->id()) {
    $forumSearch->setViewer(new Gazelle\User($LoggedUser['ID']));
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
