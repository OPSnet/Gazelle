<?php

if ($Viewer->disablePosting()) {
    error('Your posting privileges have been removed.');
}
authorize();

$thread = (new Gazelle\Manager\ForumThread)->findById((int)($_POST['threadid'] ?? 0));
if (is_null($thread)) {
    error(404);
}
$threadId = $thread->id();
$forum    = $thread->forum();

if (!$Viewer->readAccess($forum)|| !$Viewer->writeAccess($forum) || $thread->isLocked() && !$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

// If you're not sending anything, go back
$body = trim($_POST['quickpost'] ?? '');
if ($body === '') {
    header("Location: " . redirectUrl($thread->location()));
    exit;
}

if ($thread->lastAuthorId() == $Viewer->id() && isset($_POST['merge'])) {
    $postId = $thread->mergePost($Viewer->id(), $body);
} else {
    $postId = $thread->addPost($Viewer->id(), $body);
}

(new Gazelle\User\Notification\Quote($Viewer))->create(
    new Gazelle\Manager\User, $body, $postId, 'forums', $threadId
);
$subscription = new Gazelle\User\Subscription($Viewer);
if (isset($_POST['subscribe']) && !$subscription->isSubscribed($threadId)) {
    $subscription->subscribe($threadId);
}
(new Gazelle\Manager\Subscription)->flush('forums', $threadId);

header("Location: {$thread->location()}&postid=$postId#post$postId");
