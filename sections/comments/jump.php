<?php

try {
    $comment = (new Gazelle\Manager\Comment)->findById((int)($_REQUEST['postid'] ?? 0));
} catch (\Gazelle\Exception\ResourceNotFoundException $e) {
    error(404);
}
header('Location: ' . $comment->url());
