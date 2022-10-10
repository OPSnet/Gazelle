<?php

if (!$Viewer->permitted('site_admin_forums')) {
    error(403);
}

$postId = (int)($_GET['postid'] ?? 0);
if (!$postId) {
    die("bad id");
}
$pageType = ($_GET['type'] ?? '');
if (!in_array($pageType, ['forums', 'collages', 'requests', 'torrents', 'artist'])) {
    die("bad pagetype");
}
$depth = (int)($_GET['depth'] ?? 0);
if ($_GET['depth'] != $depth) {
    die("bad depth");
}

$commentMan = new Gazelle\Manager\Comment;
$history = $commentMan->loadEdits($pageType, $postId);

[$userId, $editTime] = $history[$depth];
if ($depth != 0) {
    $body = $history[$depth - 1][2];
} else {
    $body = match($pageType) {
        'forums' => (new Gazelle\Manager\ForumPost)->findById($postId)->body(),
        default  => $commentMan->findById($postId)->body(),
    };
}

echo $Twig->render('post.twig', [
    'body'      => $body,
    'depth'     => $depth,
    'edit_time' => $editTime,
    'post_id'   => $postId,
    'initial'   => $depth === count($history),
    'page_type' => $pageType,
    'user_id'   => $userId,
]);
