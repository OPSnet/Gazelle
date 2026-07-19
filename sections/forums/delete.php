<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_forum_post_delete')) {
    Error403::error();
}
authorize();

$post = new Manager\ForumPost()->findById((int)($_GET['postid'] ?? 0));
if (is_null($post)) {
    Error404::error();
}

if (!$post->remove()) {
    Error404::error();
}
