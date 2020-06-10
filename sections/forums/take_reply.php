<?php
authorize();

//TODO: Remove all the stupid queries that could get their information just as easily from the cache
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

if (!empty($LoggedUser['DisablePosting'])) {
    error('Your posting privileges have been removed.');
}

// Quick SQL injection checks

if (isset($_POST['thread']) && !is_number($_POST['thread'])) {
    error(0);
}
if (isset($_POST['forum']) && !is_number($_POST['forum'])) {
    error(0);
}

// If you're not sending anything, go back
if ($_POST['body'] === '' || !isset($_POST['body'])) {
    $Location = empty($_SERVER['HTTP_REFERER']) ? "forums.php?action=viewthread&threadid={$_POST['thread']}" : $_SERVER['HTTP_REFERER'];
    header("Location: {$Location}");
    die();
}

$TopicID = (int)$_POST['thread'];
$ThreadInfo = Forums::get_thread_info($TopicID);
if ($ThreadInfo === null) {
    error(404);
}

$ForumID = $ThreadInfo['ForumID'];
if (!Forums::check_forumperm($ForumID)) {
    error(403);
}
if (!Forums::check_forumperm($ForumID, 'Write') || $LoggedUser['DisablePosting'] || $ThreadInfo['IsLocked'] == '1' && !check_perms('site_moderate_forums')) {
    error(403);
}

$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
if (isset($_POST['subscribe']) && !$subscription->isSubscribed($TopicID)) {
    $subscription->subscribe($TopicID);
}

$forum = new \Gazelle\Forum($ForumID);

$Body = trim($_POST['body']);
if ($ThreadInfo['LastPostAuthorID'] == $LoggedUser['ID'] && isset($_POST['merge'])) {
    $PostID = $forum->mergePost($LoggedUser['ID'], $TopicID, $Body);
} else {
    $PostID = $forum->addPost($LoggedUser['ID'], $TopicID, $Body);
    ++$ThreadInfo['Posts'];
}
$CatalogueID = (int)floor((POSTS_PER_PAGE * ceil($ThreadInfo['Posts'] / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE);
$Cache->delete_value("thread_{$TopicID}_catalogue_{$CatalogueID}");
$Cache->delete_value("thread_{$TopicID}_info");

$subscription->flush('forums', $TopicID);
$subscription->quoteNotify($Body, $PostID, 'forums', $TopicID);

header("Location: forums.php?action=viewthread&threadid=$TopicID&page=".ceil($ThreadInfo['Posts']
    / ($LoggedUser['PostsPerPage'] ?? POSTS_PER_PAGE)));
