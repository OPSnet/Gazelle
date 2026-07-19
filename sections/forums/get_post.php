<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$post = new Manager\ForumPost()->findById((int)($_GET['post'] ?? 0));
if (is_null($post)) {
    Error404::error();
}
if (!$Viewer->readAccess($post->thread()->forum())) {
    Error403::error();
}
header('Content-type: text/plain');
echo $post->body();
