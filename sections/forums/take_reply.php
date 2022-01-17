<?php

if ($Viewer->disablePosting()) {
    error('Your posting privileges have been removed.');
}
authorize();

$threadId = (int)($_POST['thread'] ?? 0);
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
$ThreadInfo = $forum->threadInfo($threadId);

if (!$Viewer->readAccess($forum)|| !$Viewer->writeAccess($forum) || $ThreadInfo['isLocked'] && !$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

// If you're not sending anything, go back
$body = trim($_POST['quickpost'] ?? '');
if ($body === '') {
    header("Location: " .  $_SERVER['HTTP_REFERER'] ?? "forums.php?action=viewthread&threadid={$_POST['thread']}");
    exit;
}

if ($ThreadInfo['LastPostAuthorID'] == $Viewer->id() && isset($_POST['merge'])) {
    $postId = $forum->mergePost($Viewer->id(), $threadId, $body);
} else {
    $postId = $forum->addPost($Viewer->id(), $threadId, $body);
    ++$ThreadInfo['Posts'];
}

(new Gazelle\User\Notification\Quote($Viewer))->create(
    new Gazelle\Manager\User, $body, $postId, 'forums', $threadId
);
$subscription = new Gazelle\Subscription($Viewer);
if (isset($_POST['subscribe']) && !$subscription->isSubscribed($threadId)) {
    $subscription->subscribe($threadId);
}
(new Gazelle\Manager\Subscription)->flush('forums', $threadId);

header("Location: forums.php?action=viewthread&threadid=$threadId&page="
    . (int)ceil($ThreadInfo['Posts'] / $Viewer->postsPerPage())
    . "#post$PostID"
);
