<?php

require_once('do_warn.php');
[$post, $body] = handleWarningRequest(new Gazelle\Manager\ForumPost);
$post->edit($Viewer->id(), $body);
if ($post->isPinned()) {
    $post->thread()->flush();
}
