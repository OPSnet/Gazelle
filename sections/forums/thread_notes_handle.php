<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_moderate_forums')) {
    Error403::error();
}
authorize();

$thread = new Manager\ForumThread()->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    Error404::error();
}
$body = trim($_POST['body'] ?? '');
if (!strlen($body)) {
    Error400::error("Thread note cannot be empty");
}

$thread->addThreadNote($Viewer, $body);

header("Location: {$thread->location()}#thread_notes");
