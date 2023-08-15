<?php

$postId = (int)($_GET['post'] ?? 0);
$pm = (new Gazelle\Manager\PM($Viewer))->findByPostId($postId);
if (is_null($pm)) {
    error(403);
}

$body = $pm->postBody($postId);
if (is_null($body)) {
    error(404);
}

// This gets sent to the browser, which echoes it wherever
header('Content-type: text/plain');
echo $body;
