<?php

/**********|| Page to show individual forums || ********************************\

Things to expect in $_GET:
    ForumID: ID of the forum curently being browsed
    page:    The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

//---------- Things to sort out before it can start printing/generating content

// Check for lame SQL injection attempts
$forum = (new Gazelle\Manager\Forum())->findById((int)$_GET['forumid']);
if (is_null($forum)) {
    print json_die(['status' => 'failure']);
}

if (!$Viewer->readAccess($forum)) {
    json_die("failure", "insufficient permission");
}

if (isset($_GET['pp'])) {
    $PerPage = (int)$_GET['pp'];
} else {
    $PerPage = $Viewer->postsPerPage();
}
$Page = (int)($_GET['page'] ?? 1);

$ForumID = $forum->id();
$ForumName = $forum->name();
$threadList = $forum->tableOfContentsForum($Page);

if (!count($threadList)) {
    print json_die([
        'status'    => 'success',
        'forumName' => $ForumName,
        'threads'   => []
    ]);
}

// forums_last_read_topics is a record of the last post a user read in a topic, and what page that was on
$db   = Gazelle\DB::DB();
$args = array_keys($threadList);
$db->prepared_query("
    SELECT l.TopicID,
        l.PostID,
        ceil((SELECT count(*) FROM forums_posts AS p WHERE p.TopicID = l.TopicID AND p.ID <= l.PostID) / ?) AS Page
    FROM forums_last_read_topics AS l
    WHERE l.UserID = ?
        AND l.TopicID IN (" . placeholders($args) . ")
    ", $PerPage, $Viewer->id(), ...$args
);
$LastRead = $db->to_array('TopicID');

$JsonTopics = [];
$userCache = [];
$userMan = new Gazelle\Manager\User();
foreach ($threadList as $thread) {
    [$threadId, $Title, $AuthorID, $Locked, $Sticky, $PostCount, $LastID, $LastTime, $LastAuthorID] = array_values($thread);

    // handle read/unread posts - the reason we can't cache the whole page
    $unread = (!$Locked || $Sticky)
        && (
            (empty($LastRead[$threadId]) || $LastRead[$threadId]['PostID'] < $LastID)
            && strtotime($LastTime) > $Viewer->forumCatchupEpoch()
        );

    if (!isset($userCache[$AuthorID])) {
        $userCache[$AuthorID] = $userMan->findById($AuthorID);
    }
    $author = $userCache[$AuthorID];
    if (!isset($userCache[$LastAuthorID])) {
        $userCache[$LastAuthorID] = $userMan->findById($LastAuthorID);
    }
    $lastAuthor = $userCache[$LastAuthorID];

    $JsonTopics[] = [
        'topicId'        => $threadId,
        'title'          => display_str($Title),
        'authorId'       => $AuthorID,
        'authorName'     => $author ? $author->username() : null,
        'locked'         => $Locked == 1,
        'sticky'         => $Sticky == 1,
        'postCount'      => $PostCount,
        'lastID'         => $LastID ?? 0,
        'lastTime'       => $LastTime,
        'lastAuthorId'   => $LastAuthorID ?? 0,
        'lastAuthorName' => $lastAuthor ? $lastAuthor->username() : '',
        'lastReadPage'   => (int)($LastRead[$threadId]['Page'] ?? 0),
        'lastReadPostId' => (int)($LastRead[$threadId]['PostID'] ?? 0),
        'read'           => !$unread,
    ];
}

print json_encode([
    'status' => 'success',
    'response' => [
        'forumName'   => $ForumName,
        'currentPage' => $Page,
        'pages'       => (int)ceil($forum->numThreads() / TOPICS_PER_PAGE),
        'threads'     => $JsonTopics
    ]
]);
