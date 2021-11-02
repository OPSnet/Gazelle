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
$Body = trim($_POST['quickpost'] ?? '');
if ($Body === '') {
    header("Location: " .  $_SERVER['HTTP_REFERER'] ?? "forums.php?action=viewthread&threadid={$_POST['thread']}");
    exit;
}

if ($ThreadInfo['LastPostAuthorID'] == $Viewer->id() && isset($_POST['merge'])) {
    $PostID = $forum->mergePost($Viewer->id(), $threadId, $Body);
} else {
    $PostID = $forum->addPost($Viewer->id(), $threadId, $Body);
    ++$ThreadInfo['Posts'];
}

$subscription = new Gazelle\Subscription($Viewer);
$subscription->quoteNotify($Body, $PostID, 'forums', $threadId);
if (isset($_POST['subscribe']) && !$subscription->isSubscribed($threadId)) {
    $subscription->subscribe($threadId);
}
(new Gazelle\Manager\Subscription)->flush('forums', $threadId);

header("Location: forums.php?action=viewthread&threadid=$threadId&page="
    . (int)ceil($ThreadInfo['Posts'] / $Viewer->postsPerPage())
    . "#post$PostID"
);
