<?php

$post = (new Gazelle\Manager\ForumPost)->findById((int)($_GET['post'] ?? 0));
if (is_null($post)) {
    error(404);
}
if (!$Viewer->readAccess($post->thread()->forum())) {
    error(403);
}
echo display_str($post->body());
