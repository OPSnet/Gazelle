<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

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
    Error403::error('Your posting privileges have been removed.');
}
authorize();

if (!isset($_POST['forum'])) {
    Error400::error('Forum ID not specified');
}
$forum = new Manager\Forum()->findById((int)$_POST['forum']);
if (is_null($forum)) {
    Error404::error();
}
if (!$Viewer->writeAccess($forum) || !$Viewer->createAccess($forum)) {
    Error403::error();
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
        $answerList[(int)$i + 1] = $Answer;
    }

    if (count($answerList) < 2) {
        Error400::error('You cannot create a poll with only one answer.');
    }
    if (count($answerList) > 25) {
        Error400::error('You cannot create a poll with more than 25 answers.');
    }
}

$thread = new Manager\ForumThread()->create($forum, $Viewer, $title, $body);
if ($needPoll) {
    new Manager\ForumPoll()->create($thread, $question, $answerList);
    if ($forum->id == STAFF_FORUM_ID) {
        Irc::sendMessage(
            IRC_CHAN_STAFF,
            "Poll created by {$Viewer->username()}: \"$question\" " . $thread->publicLocation()
        );
    }
}

if (isset($_POST['subscribe'])) {
    new User\Subscription($Viewer)->subscribe($thread);
}
$userMan = new Manager\User();
foreach ($forum->autoSubscribeUserIdList() as $userId) {
    $user = $userMan->findById($userId);
    if ($user) {
        new User\Subscription($user)->subscribe($thread);
    }
}

header("Location: {$thread->location()}");
