<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableForums()) {
    json_die('failure');
}

$showUnread = (bool)($_GET['showunread'] ?? true);

$forMan = new Manager\Forum();
$paginator = new Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    $showUnread ? $forMan->unreadSubscribedForumTotal($Viewer) : $forMan->subscribedForumTotal($Viewer)
);

json_print('success', [
    'threads' => $forMan->latestPostsList($Viewer, $showUnread, $paginator->limit(), $paginator->offset())
]);
