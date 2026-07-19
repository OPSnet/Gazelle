<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableForums()) {
    Error403::error();
}

$user = empty($_GET['userid']) ? $Viewer : new Manager\User()->findById((int)$_GET['userid']);
if (is_null($user)) {
    Error404::error();
}
$forumSearch = new Search\Forum($user);
if ($Viewer->id !== $user->id) {
    $forumSearch->setViewer($Viewer);
}

$paginator = new Util\Paginator(TOPICS_PER_PAGE, (int)($_REQUEST['page'] ?? 1));
$paginator->setTotal($forumSearch->threadsByUserTotal());

echo $Twig->render('user/thread-history.twig', [
    'page'      => $forumSearch->threadsByUserPage($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'user'      => $user,
]);
