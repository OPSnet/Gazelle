<?php

/**********|| Page to show individual threads || ********************************\

Things to expect in $_GET:
    ThreadID: ID of the forum curently being browsed
    page:    The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

$userMan = new Gazelle\Manager\User;

//---------- Things to sort out before it can start printing/generating content

// Check for lame SQL injection attempts
if (!isset($_GET['threadid']) && isset($_GET['topicid'])) {
    $_GET['threadid'] = $_GET['topicid'];
}
$forumMan = new Gazelle\Manager\Forum;
if (isset($_GET['postid'])) {
    $postId = (int)$_GET['postid'];
    $forum = $forumMan->findByPostId($postId);
    if (is_null($forum)) {
        print json_die('failure', 'bad post id');
    }
    $thread = (new Gazelle\Manager\ForumThread)->findById($forumMan->findThreadIdByPostId($postId));
} elseif (isset($_GET['threadid'])) {
    $postId = false;
    $thread = (new Gazelle\Manager\ForumThread)->findById((int)$_GET['threadid']);
    if (is_null($thread)) {
        print json_die('failure', 'bad thread id');
    }
    $forum = $thread->forum();
} else {
    print json_die('failure', 'no post or thread id');
}

// Make sure they're allowed to look at the page
if (!$Viewer->readAccess($forum)) {
    print json_die('failure', 'access denied');
}

$forumId = $forum->id();
$perPage = $_GET['pp'] ?? $Viewer->postsPerPage();

//Post links utilize the catalogue & key params to prevent issues with custom posts per page
if ($thread->postTotal() <= $perPage) {
    $PostNum = 1;
} else {
    if ((int)$_GET['post']) {
        $PostNum = (int)$_GET['post'];
    } elseif ($postId) {
        $PostNum = $forum->priorPostTotal($postId);
    } else {
        $PostNum = 1;
    }
}

$paginator = new Gazelle\Util\Paginator($perPage, (int)($_GET['page'] ?? ceil($PostNum / $perPage)));
$paginator->setTotal($thread->postTotal());

$slice = $thread->slice(page: $paginator->page(), perPage: $perPage);

if ($_GET['updatelastread'] !== '0') {
    $LastPost = end($slice);
    $LastPost = $LastPost['ID'];
    reset($slice);
    if ($thread->postTotal() <= $perPage * $paginator->page() && $thread->pinnedPostId() > $LastPost) {
        $LastPost = $thread->pinnedPostId();
    }
    //Handle last read
    if (!$thread->isLocked() || $thread->isPinned()) {
        $LastRead = $DB->scalar("
            SELECT PostID
            FROM forums_last_read_topics
            WHERE UserID = ?
                AND TopicID = ?
            ", $Viewer->id(), $thread->id()
        );
        if ($LastRead < $LastPost) {
            $DB->prepared_query("
                INSERT INTO forums_last_read_topics
                       (UserID, TopicID, PostID)
                VALUES (?,      ?,       ?)
                ON DUPLICATE KEY UPDATE PostID = ?
                ", $Viewer->id(), $thread->id(), $LastPost, $LastPost
            );
        }
    }
}

$JsonPoll = null;
if ($thread->hasPoll()) {
    [$Question, $Answers, $Votes, $Featured, $Closed] = $thread->pollData();
    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    //Polls lose the you voted arrow thingy
    $UserResponse = $thread->pollResponse($Viewer->id());
    if ($UserResponse > 0) {
        $Answers[$UserResponse] = '&raquo; ' . $Answers[$UserResponse];
    } else {
        if (!empty($UserResponse) && $forum->hasRevealVotes()) {
            $Answers[$UserResponse] = '&raquo; ' . $Answers[$UserResponse];
        }
    }

    $JsonPoll = [
        'answers'    => [],
        'closed'     => $Closed == 1,
        'featured'   => (bool)$Featured,
        'question'   => $Question,
        'maxVotes'   => $MaxVotes,
        'totalVotes' => $TotalVotes,
        'voted'      => $UserResponse !== null || $Closed || $thread->isLocked(),
        'vote'       => $UserResponse ? $UserResponse - 1 : null,
    ];

    foreach ($Answers as $i => $Answer) {
        if (!empty($Votes[$i]) && $TotalVotes > 0) {
            $Ratio = $Votes[$i] / $MaxVotes;
            $Percent = $Votes[$i] / $TotalVotes;
        } else {
            $Ratio = 0;
            $Percent = 0;
        }
        $JsonPoll['answers'][] = [
            'answer'  => $Answer,
            'ratio'   => $Ratio,
            'percent' => $Percent,
        ];
    }
}

// Squeeze in stickypost
if ($thread->pinnedPostId()) {
    if ($thread->pinnedPostId() != $slice[0]['ID']) {
        array_unshift($slice, $thread->pinnedPostInfo());
    }
    if ($thread->pinnedPostId() != $slice[count($slice) - 1]['ID']) {
        $slice[] = $thread->pinnedPostInfo();
    }
}

$userCache = [];
$JsonPosts = [];
foreach ($slice as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime] = array_values($Post);
    if (!isset($userCache[$AuthorID])) {
        $userCache[$AuthorID] = $userMan->findById((int)$AuthorID);
    }
    $author = $userCache[$AuthorID];
    if (!isset($userCache[$EditedUserID])) {
        $userCache[$EditedUserID] = $userMan->findById((int)$EditedUserID);
    }
    $editor = $userCache[$EditedUserID];

    $JsonPosts[] = [
        'postId'         => $PostID,
        'addedTime'      => $AddedTime,
        'bbBody'         => $Body,
        'body'           => Text::full_format($Body),
        'editedUserId'   => $EditedUserID,
        'editedTime'     => $EditedTime,
        'editedUsername' => $editor ? $editor->username() : null,
        'author' => [
            'authorId'   => $AuthorID,
            'authorName' => $author->username(),
            'paranoia'   => $author->paranoia(),
            'donor'      => $author->isDonor(),
            'warned'     => $author->isWarned(),
            'avatar'     => $author->avatar(),
            'enabled'    => $author->isEnabled(),
            'userTitle'  => $author->title(),
        ],
    ];
}

$subscribed = (new Gazelle\Subscription($Viewer))->isSubscribed($thread->id());
if ($subscribed) {
    $Cache->delete_value('subscriptions_user_new_' . $Viewer->id());
}

print json_encode([
    'status' => 'success',
    'response' => [
        'forumId'     => $forumId,
        'forumName'   => $forum->name(),
        'threadId'    => $thread->id(),
        'threadTitle' => $thread->title(),
        'subscribed'  => $subscribed,
        'locked'      => $thread->isLocked(),
        'sticky'      => $thread->isPinned(),
        'currentPage' => $paginator->page(),
        'pages'       => $paginator->pages(),
        'poll'        => empty($JsonPoll) ? null : $JsonPoll,
        'posts'       => $JsonPosts,
    ]
]);
