<?php
authorize();

/*********************************************************************\
//--------------Take Post--------------------------------------------//

This page takes a forum post submission, validates it (TODO), and
enters it into the database. The user is then redirected to their
post.

$_POST['action'] is what the user is trying to do. It can be:

'reply' if the user is replying to a thread
    It will be accompanied with:
    $_POST['thread']
    $_POST['body']


\*********************************************************************/

$user = new Gazelle\User($LoggedUser['ID']);
if ($user->disablePosting()) {
    error('Your posting privileges have been removed.');
}

$threadId = (int)($_POST['thread'] ?? 0);
$forum = (new Gazelle\Manager\Forum)->findByThreadId($threadId);
if (is_null($forum)) {
    error(404);
}
$ThreadInfo = $forum->threadInfo($threadId);

if (!$user->readAccess($forum)|| !$user->writeAccess($forum) || $ThreadInfo['isLocked'] && !check_perms('site_moderate_forums')) {
    error(403);
}

// If you're not sending anything, go back
$Body = trim($_POST['body'] ?? '');
if ($Body === '') {
    header("Location: " .  $_SERVER['HTTP_REFERER'] ?? "forums.php?action=viewthread&threadid={$_POST['thread']}");
    exit;
}

if ($ThreadInfo['LastPostAuthorID'] == $LoggedUser['ID'] && isset($_POST['merge'])) {
    $PostID = $forum->mergePost($LoggedUser['ID'], $threadId, $Body);
} else {
    $PostID = $forum->addPost($LoggedUser['ID'], $threadId, $Body);
    ++$ThreadInfo['Posts'];
}
$CatalogueID = (int)floor((POSTS_PER_PAGE * ceil($ThreadInfo['Posts'] / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE);
$Cache->deleteMulti(["thread_{$threadId}_catalogue_{$CatalogueID}", "thread_{$threadId}_info"]);

$subscription = new Gazelle\Manager\Subscription($LoggedUser['ID']);
if (isset($_POST['subscribe']) && !$subscription->isSubscribed($threadId)) {
    $subscription->subscribe($threadId);
}
$subscription->flush('forums', $threadId);
$subscription->quoteNotify($Body, $PostID, 'forums', $threadId);

header("Location: forums.php?action=viewthread&threadid=$threadId&page="
    . (int)ceil($ThreadInfo['Posts'] / ($LoggedUser['PostsPerPage'] ?? POSTS_PER_PAGE))
    . "#post$PostID"
);
