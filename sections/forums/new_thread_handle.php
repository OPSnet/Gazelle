<?php

use Gazelle\Util\Irc;

/* Creating a new thread
 *   Form variables:
 *      $_POST['forum']
 *      $_POST['title']
 *      $_POST['body']
 *    optional for a poll:
 *      $_POST['question']
 *      $_POST['answers'] (array of answers)
 */

if ($Viewer->disablePosting()) {
    error('Your posting privileges have been removed.');
}
authorize();

if (!isset($_POST['forum'])) {
    error(0);
}
$forum = (new Gazelle\Manager\Forum)->findById((int)$_POST['forum']);
if (is_null($forum)) {
    error(404);
}
if (!$Viewer->writeAccess($forum) || !$Viewer->createAccess($forum)) {
    error(403);
}

// If you're not sending anything, go back
if (empty($_POST['body']) || empty($_POST['title'])) {
    header('Location: ' . redirectUrl($forum->location()));
    exit;
}
$title = shortenString(trim($_POST['title']), 150, true, false);
$body = trim($_POST['body']);

if (empty($_POST['question']) || empty($_POST['answers']) || !$Viewer->permitted('forums_polls_create')) {
    $needPoll = false;
} else {
    $needPoll   = true;
    $question   = trim($_POST['question']);
    $answerList = [];

    // Step over empty answer fields to avoid gaps in the answer IDs
    foreach ($_POST['answers'] as $i => $Answer) {
        if ($Answer == '') {
            continue;
        }
        $answerList[$i + 1] = $Answer;
    }

    if (count($answerList) < 2) {
        error('You cannot create a poll with only one answer.');
    }
    if (count($answerList) > 25) {
        error('You cannot create a poll with more than 25 answers.');
    }
}

$thread = (new Gazelle\Manager\ForumThread)->create(
    forum:  $forum,
    userId: $Viewer->id(),
    title:  $title,
    body:   $body,
);
$threadId = $thread->id();
if ($needPoll) {
    (new Gazelle\Manager\ForumPoll)->create($threadId, $question, $answerList);
    if ($forum->id() == STAFF_FORUM_ID) {
        Irc::sendMessage(
            IRC_CHAN_STAFF,
            "Poll created by {$Viewer->username()}: \"$question\" " . $thread->publicLocation()
        );
    }
}

if (isset($_POST['subscribe'])) {
    (new Gazelle\User\Subscription($Viewer))->subscribe($threadId);
}
$userMan = new Gazelle\Manager\User;
foreach ($forum->autoSubscribeUserIdList() as $userId) {
    $user = $userMan->findById($userId);
    if ($user) {
        (new Gazelle\User\Subscription($user))->subscribe($threadId);
    }
}

header("Location: {$thread->location()}");
