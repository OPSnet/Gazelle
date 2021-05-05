<?php

authorize();
if ($LoggedUser['DisablePosting']) {
    error('Your posting privileges have been removed.');
}

$page = $_REQUEST['page'] ?? null;
if (!in_array($page, ['artist', 'collages', 'requests', 'torrents'])) {
    error(403);
}

$pageId = (int)($_REQUEST['pageid'] ?? 0);
if (!$pageId) {
    error(404);
}

$commentMan = new Gazelle\Manager\Comment;
$comment = $commentMan->create($LoggedUser['ID'], $page, $pageId, $_POST['quickpost']);

$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
if (isset($_POST['subscribe']) && !$subscription->isSubscribedComments($page, $pageId)) {
    $subscription->subscribeComments($page, $pageId);
}

header("Location: " . $comment->url());
