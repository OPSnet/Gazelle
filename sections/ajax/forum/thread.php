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

if (isset($_GET['postid'])) {
    $post = (new Gazelle\Manager\ForumPost)->findById((int)$_GET['postid']);
    if (is_null($post)) {
        json_error('bad post id');
    }
    $thread = $post->thread();
} elseif (isset($_GET['threadid'])) {
    $post = false;
    $thread = (new Gazelle\Manager\ForumThread)->findById((int)$_GET['threadid']);
    if (is_null($thread)) {
        json_error('bad thread id');
    }
} else {
    json_error('no post or thread id');
}
$forum = $thread->forum();

// Make sure they're allowed to look at the page
if (!$Viewer->readAccess($forum)) {
    json_error('access denied');
}

$forumId = $forum->id();
$perPage = $_GET['pp'] ?? $Viewer->postsPerPage();

//Post links utilize the catalogue & key params to prevent issues with custom posts per page
$PostNum = match(true) {
    isset($_GET['post'])        => (int)$_GET['post'],
    $post && !$post->isSticky() => $post->priorPostTotal(),
    default                     => 1,
};

$paginator = new Gazelle\Util\Paginator($perPage, (int)($_GET['page'] ?? ceil($PostNum / $perPage)));
$paginator->setTotal($thread->postTotal());

$slice = $thread->slice(page: $paginator->page(), perPage: $perPage);
$db    = Gazelle\DB::DB();

if ($_GET['updatelastread'] !== '0') {
    $LastPost = end($slice);
    $LastPost = $LastPost['ID'];
    reset($slice);
    if ($thread->postTotal() <= $perPage * $paginator->page() && $thread->pinnedPostId() > $LastPost) {
        $LastPost = $thread->pinnedPostId();
    }
    //Handle last read
    if (!$thread->isLocked() || $thread->isPinned()) {
        $LastRead = $db->scalar("
            SELECT PostID
            FROM forums_last_read_topics
            WHERE UserID = ?
                AND TopicID = ?
            ", $Viewer->id(), $thread->id()
        );
        if ($LastRead < $LastPost) {
            $db->prepared_query("
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
    $poll = new Gazelle\ForumPoll($thread->id());

    $response = $poll->response($Viewer->id());
    $answerList = $poll->vote();
    if ($response > 0 || (!is_null($response) && $poll->hasRevealVotes())) {
        $answerList[$response]['asnswer'] = '&raquo; ' . $answerList[$response]['asnswer'];
    }

    $JsonPoll = [
        'answers'    => $answerList,
        'closed'     => $poll->isClosed(),
        'featured'   => $poll->isFeatured(),
        'question'   => $poll->question(),
        'maxVotes'   => $poll->max(),
        'totalVotes' => $poll->total(),
        'voted'      => $response !== null || $poll->isClosed() || $thread->isLocked(),
        'vote'       => $response ? $response - 1 : null,
    ];
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
foreach ($slice as $Post) {
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
            'donor'      => (new Gazelle\User\Privilege($author))->isDonor(),
            'warned'     => $author->isWarned(),
            'avatar'     => $author->avatar(),
            'enabled'    => $author->isEnabled(),
            'userTitle'  => $author->title(),
        ],
    ];
}

$subscribed = (new Gazelle\User\Subscription($Viewer))->isSubscribed($thread->id());
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
        'poll'        => $JsonPoll,
        'posts'       => $JsonPosts,
    ]
]);
