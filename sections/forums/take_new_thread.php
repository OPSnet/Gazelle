<?php
authorize();

/*
'new' if the user is creating a new thread
    It will be accompanied with:
    $_POST['forum']
    $_POST['title']
    $_POST['body']

    and optionally include:
    $_POST['question']
    $_POST['answers']
    the latter of which is an array
*/

if ($LoggedUser['DisablePosting']) {
    error('Your posting privileges have been removed.');
}

if (isset($_POST['thread']) && !is_number($_POST['thread'])) {
    error(0);
}

if (isset($_POST['forum']) && !is_number($_POST['forum'])) {
    error(0);
}
$ForumID = $_POST['forum'];
if (!isset($Forums[$ForumID])) {
    error(404);
}
if (!Forums::check_forumperm($ForumID, 'Write') || !Forums::check_forumperm($ForumID, 'Create')) {
    error(403);
}

// If you're not sending anything, go back
if (empty($_POST['body']) || empty($_POST['title'])) {
    $Location = (empty($_SERVER['HTTP_REFERER'])) ? "forums.php?action=viewforum&forumid={$_POST['forum']}": $_SERVER['HTTP_REFERER'];
    header("Location: {$Location}");
    die();
}

$Title = Format::cut_string(trim($_POST['title']), 150, 1, 0);
$Body = trim($_POST['body']);

if (empty($_POST['question']) || empty($_POST['answers']) || !check_perms('forums_polls_create')) {
    $needPoll = false;
} else {
    $needPoll = true;
    $Question = trim($_POST['question']);
    $Answers = [];
    $Votes = [];

    // Step over empty answer fields to avoid gaps in the answer IDs
    foreach ($_POST['answers'] as $i => $Answer) {
        if ($Answer == '') {
            continue;
        }
        $Answers[$i + 1] = $Answer;
        $Votes[$i + 1] = 0;
    }

    if (count($Answers) < 2) {
        error('You cannot create a poll with only one answer.');
    } elseif (count($Answers) > 25) {
        error('You cannot create a poll with greater than 25 answers.');
    }
}

$forum = new \Gazelle\Forum($ForumID);
$threadId = $forum->addThread($LoggedUser['ID'], $Title, $Body);

if ($needPoll) {
    $forum->addPoll($threadId, $Question, $Answers);
    $Cache->cache_value("polls_$threadId", [$Question, $Answers, $Votes, null, 0], 0);
    if ($ForumID == STAFF_FORUM) {
        send_irc('PRIVMSG '.MOD_CHAN.' :!mod Poll created by '.$LoggedUser['Username'].": \"$Question\" ".site_url()."forums.php?action=viewthread&threadid=$threadId");
    }
}

if (isset($_POST['subscribe'])) {
    $subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
    $subscription->subscribe($threadId);
}

header("Location: forums.php?action=viewthread&threadid=$threadId");
