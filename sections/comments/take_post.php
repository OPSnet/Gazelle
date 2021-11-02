<?php

if ($Viewer->disablePosting()) {
    error('Your posting privileges have been removed.');
}
authorize();

$page = $_REQUEST['page'] ?? null;
if (!in_array($page, ['artist', 'collages', 'requests', 'torrents'])) {
    error(403);
}

$pageId = (int)($_REQUEST['pageid'] ?? 0);
if (!$pageId) {
    error(404);
}

$commentMan = new Gazelle\Manager\Comment;
$comment = $commentMan->create($Viewer->id(), $page, $pageId, $_POST['quickpost']);

$subscription = new \Gazelle\Subscription($Viewer);
if (isset($_POST['subscribe']) && !$subscription->isSubscribedComments($page, $pageId)) {
    $subscription->subscribeComments($page, $pageId);
}

header('Location: ' . $comment->url());
