<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$postId = (int)($_GET['post'] ?? 0);
$pm = new Manager\PM($Viewer)->findByPostId($postId);
if (is_null($pm)) {
    Error403::error();
}

$body = $pm->postBody($postId);
if (is_null($body)) {
    Error404::error();
}

// This gets sent to the browser, which echoes it wherever
header('Content-type: text/plain');
echo $body;
