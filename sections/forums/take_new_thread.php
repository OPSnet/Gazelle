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
list($threadId, $postId) = $forum->addThread($LoggedUser['ID'], $Title, $Body);

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

// if cache exists modify it, if not, then it will be correct when selected next, and we can skip this block
$sqltime = sqltime(); // Not precise but it's just for the cache; no kittens will die
if ($Forum = $Cache->get_value("forums_$ForumID")) {
    list($Forum,,,$Stickies) = $Forum;

    // Remove the last thread from the index
    if (count($Forum) == TOPICS_PER_PAGE && $Stickies < TOPICS_PER_PAGE) {
        array_pop($Forum);
    }

    if ($Stickies > 0) {
        $Part1 = array_slice($Forum, 0, $Stickies, true); // Stickies
        $Part3 = array_slice($Forum, $Stickies, TOPICS_PER_PAGE - $Stickies - 1, true); // Rest of page
    } else {
        $Part1 = [];
        $Part3 = $Forum;
    }
    $Part2 = [$threadId => [
        'ID'               => $threadId,
        'Title'            => $Title,
        'AuthorID'         => $LoggedUser['ID'],
        'IsLocked'         => 0,
        'IsSticky'         => 0,
        'NumPosts'         => 1,
        'LastPostID'       => $postId,
        'LastPostTime'     => $sqltime,
        'LastPostAuthorID' => $LoggedUser['ID'],
        'NoPoll'           => $needPoll ? 0 : 1,
    ]]; // Bumped
    $Forum = $Part1 + $Part2 + $Part3;

    $Cache->cache_value("forums_$ForumID", [$Forum, '', 0, $Stickies], 0);

    // Update the forum root
    $Cache->begin_transaction('forums_list');
    $Cache->update_row($ForumID, [
        'NumPosts'         => '+1',
        'NumTopics'        => '+1',
        'LastPostID'       => $postId,
        'LastPostAuthorID' => $LoggedUser['ID'],
        'LastPostTopicID'  => $threadId,
        'LastPostTime'     => $sqltime,
        'Title'            => $Title,
        'IsLocked'         => 0,
        'IsSticky'         => 0
        ]);
    $Cache->commit_transaction(0);
} else {
    // If there's no cache, we have no data, and if there's no data
    $Cache->delete_value('forums_list');
}

$Cache->begin_transaction("thread_$threadId".'_catalogue_0');
$Post = [
    'ID'           => $postId,
    'AuthorID'     => $LoggedUser['ID'],
    'AddedTime'    => $sqltime,
    'Body'         => $Body,
    'EditedUserID' => 0,
    'EditedTime'   => null,
];
$Cache->insert('', $Post);
$Cache->commit_transaction(0);

$Cache->begin_transaction("thread_$threadId".'_info');
$Cache->update_row(false, [
    'Posts'            => '+1',
    'LastPostAuthorID' => $LoggedUser['ID'],
    'LastPostTime'     => $sqltime
]);
$Cache->commit_transaction(0);

header("Location: forums.php?action=viewthread&threadid=$threadId");
