<?php

$body = (new Gazelle\Manager\Comment())->findBodyById((int)($_GET['postid'] ?? 0));
if (is_null($body)) {
    error(404);
}

header('Content-type: text/plain');
echo $body;
