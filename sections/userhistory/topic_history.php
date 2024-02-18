<?php

if ($Viewer->disableForums()) {
    error(403);
}

$user = empty($_GET['userid']) ? $Viewer : (new Gazelle\Manager\User())->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}
$forumSearch = new Gazelle\Search\Forum($user);
if ($Viewer->id() != $user->id()) {
    $forumSearch->setViewer($Viewer);
}

$paginator = new Gazelle\Util\Paginator(TOPICS_PER_PAGE, (int)($_REQUEST['page'] ?? 1));
$paginator->setTotal($forumSearch->threadsByUserTotal());

echo $Twig->render('user/thread-history.twig', [
    'page'      => $forumSearch->threadsByUserPage($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'user'      => $user,
]);
