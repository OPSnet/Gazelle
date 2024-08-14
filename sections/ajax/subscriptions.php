<?php
/** @phpstan-var \Gazelle\User $Viewer */

if ($Viewer->disableForums()) {
    json_die('failure');
}

$showUnread = (bool)($_GET['showunread'] ?? true);

$forMan = new Gazelle\Manager\Forum();
$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    $showUnread ? $forMan->unreadSubscribedForumTotal($Viewer) : $forMan->subscribedForumTotal($Viewer)
);

json_print('success', [
    'threads' => $forMan->latestPostsList($Viewer, $showUnread, $paginator->limit(), $paginator->offset())
]);
