<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (isset($_GET['postid'])) {
    $post = (new Gazelle\Manager\ForumPost())->findById((int)$_GET['postid']);
    if (is_null($post)) {
        json_error('bad post id');
    }
    $thread = $post->thread();
} elseif (isset($_GET['threadid']) || isset($_GET['topicid'])) {
    $post = false;
    $thread = (new Gazelle\Manager\ForumThread())
        ->findById((int)($_GET['threadid'] ?? $_GET['topicid'] ?? 0));
    if (is_null($thread)) {
        json_error('bad thread id');
    }
} else {
    json_error('no post or thread id');
}
$forum = $thread->forum();

// Make sure they are allowed to look at the page
if (!$Viewer->readAccess($thread->forum())) {
    json_error('access denied');
}

// Post links utilize the catalogue & key params to prevent issues with custom posts per page
$postNum = match (true) {
    isset($_GET['post'])        => (int)$_GET['post'],
    $post && !$post->isPinned() => $post->priorPostTotal(),
    default                     => 1,
};
$perPage = (int)($_GET['pp'] ?? $Viewer->postsPerPage());
$paginator = new Gazelle\Util\Paginator(
    $perPage,
    (int)($_GET['page'] ?? ceil($postNum / $perPage)),
);

echo (new Gazelle\Json\ForumThread(
    $thread,
    $Viewer,
    $paginator,
    isset($_GET['updatelastread']),
    new Gazelle\Manager\User(),
))->response();
