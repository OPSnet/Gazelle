<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_moderate_forums')) {
    Error403::error();
}
authorize();

$comment = new Manager\Comment()->findById((int)($_REQUEST['postid'] ?? 0));
if (is_null($comment)) {
    Error404::error();
}
$comment->remove();
