<?php

require_once('do_warn.php');
[$post, $body] = handleWarningRequest(new Gazelle\Manager\ForumPost);
$post->edit($Viewer, $body);
if ($post->isPinned()) {
    $post->thread()->flush();
}
