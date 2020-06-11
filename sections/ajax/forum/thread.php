<?php
//TODO: Normalize thread_*_info don't need to waste all that ram on things that are already in other caches
/**********|| Page to show individual threads || ********************************\

Things to expect in $_GET:
    ThreadID: ID of the forum curently being browsed
    page:    The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

//---------- Things to sort out before it can start printing/generating content

// Check for lame SQL injection attempts
if (!isset($_GET['threadid']) || !is_number($_GET['threadid'])) {
    if (isset($_GET['topicid']) && is_number($_GET['topicid'])) {
        $ThreadID = $_GET['topicid'];
    } elseif (isset($_GET['postid']) && is_number($_GET['postid'])) {
        $ThreadID = $DB->scalar("
            SELECT TopicID
            FROM forums_posts
            WHERE ID = ?
            ", $_GET['postid']
        );
        if ($ThreadID) {
            //Redirect postid to threadid when necessary.
            header("Location: ajax.php?action=forum&type=viewthread&threadid=$ThreadID&postid=$_GET[postid]");
            die();
        } else {
            print json_encode(['status' => 'failure']);
            die();
        }
    } else {
        print json_encode(['status' => 'failure']);
        die();
    }
} else {
    $ThreadID = $_GET['threadid'];
}

if (isset($_GET['pp'])) {
    $PerPage = $_GET['pp'];
} elseif (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

//---------- Get some data to start processing

// Thread information, constant across all pages
$ThreadInfo = Forums::get_thread_info($ThreadID, true, true);
if ($ThreadInfo === null) {
    json_die('failure', 'no such thread exists');
}
$ForumID = $ThreadInfo['ForumID'];

// Make sure they're allowed to look at the page
if (!Forums::check_forumperm($ForumID)) {
    print json_encode(['status' => 'failure']);
    die();
}

//Post links utilize the catalogue & key params to prevent issues with custom posts per page
if ($ThreadInfo['Posts'] > $PerPage) {
    if (isset($_GET['post']) && is_number($_GET['post'])) {
        $PostNum = $_GET['post'];
    } elseif (isset($_GET['postid']) && is_number($_GET['postid'])) {
        $PostNum = $DB->scalar("
            SELECT count(*)
            FROM forums_posts
            WHERE TopicID = $ThreadID
                AND ID <= ?
            ", $_GET['postid']
        );
    } else {
        $PostNum = 1;
    }
} else {
    $PostNum = 1;
}
list($Page, $Limit) = Format::page_limit($PerPage, min($ThreadInfo['Posts'], $PostNum));
if (($Page - 1) * $PerPage > $ThreadInfo['Posts']) {
    $Page = ceil($ThreadInfo['Posts'] / $PerPage);
}
list($CatalogueID,$CatalogueLimit) = Format::catalogue_limit($Page, $PerPage, THREAD_CATALOGUE);

// Cache catalogue from which the page is selected, allows block caches and future ability to specify posts per page
if (!$Catalogue = $Cache->get_value("thread_$ThreadID"."_catalogue_$CatalogueID")) {
    $DB->prepared_query("
        SELECT
            p.ID,
            p.AuthorID,
            p.AddedTime,
            p.Body,
            p.EditedUserID,
            p.EditedTime
        FROM forums_posts AS p
        WHERE p.TopicID = ?
            AND p.ID != ?
        LIMIT ?
        ", $ThreadID, $ThreadInfo['StickyPostID'], $CatalogueLimit
    );
    $Catalogue = $DB->to_array(false, MYSQLI_ASSOC);
    if (!$ThreadInfo['IsLocked'] || $ThreadInfo['IsSticky']) {
        $Cache->cache_value("thread_$ThreadID"."_catalogue_$CatalogueID", $Catalogue, 0);
    }
}
$Thread = Format::catalogue_select($Catalogue, $Page, $PerPage, THREAD_CATALOGUE);

if ($_GET['updatelastread'] !== '0') {
    $LastPost = end($Thread);
    $LastPost = $LastPost['ID'];
    reset($Thread);
    if ($ThreadInfo['Posts'] <= $PerPage * $Page && $ThreadInfo['StickyPostID'] > $LastPost) {
        $LastPost = $ThreadInfo['StickyPostID'];
    }
    //Handle last read
    if (!$ThreadInfo['IsLocked'] || $ThreadInfo['IsSticky']) {
        $LastRead = $DB->scalar("
            SELECT PostID
            FROM forums_last_read_topics
            WHERE UserID = ?
                AND TopicID = ?
            ", $LoggedUser['ID'], $ThreadID
        );
        if ($LastRead < $LastPost) {
            $DB->prepared_query("
                INSERT INTO forums_last_read_topics
                       (UserID, TopicID, PostID)
                VALUES (?,      ?,       ?)
                ON DUPLICATE KEY UPDATE PostID = ?
                ", $LoggedUser['ID'], $ThreadID, $LastPost, $LastPost
            );
        }
    }
}

//Handle subscriptions
$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
$UserSubscriptions = $subscription->subscriptions();

if (in_array($ThreadID, $UserSubscriptions)) {
    $Cache->delete_value('subscriptions_user_new_'.$LoggedUser['ID']);
}

$JsonPoll = [];
if ($ThreadInfo['NoPoll'] == 0) {
    $forum = new \Gazelle\Forum($ForumID);
    list($Question, $Answers, $Votes, $Featured, $Closed) = $forum->pollData($threadId);
    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $RevealVoters = in_array($ForumID, $ForumsRevealVoters);
    //Polls lose the you voted arrow thingy
    $UserResponse = $DB->scalar("
        SELECT Vote
        FROM forums_polls_votes
        WHERE UserID = ?
            AND TopicID = ?
        ", $LoggedUser['ID'], $ThreadID
    );
    if ($UserResponse > 0) {
        $Answers[$UserResponse] = '&raquo; '.$Answers[$UserResponse];
    } else {
        if (!empty($UserResponse) && $RevealVoters) {
            $Answers[$UserResponse] = '&raquo; '.$Answers[$UserResponse];
        }
    }

    $JsonPoll['closed'] = ($Closed == 1);
    $JsonPoll['featured'] = $Featured;
    $JsonPoll['question'] = $Question;
    $JsonPoll['maxVotes'] = (int)$MaxVotes;
    $JsonPoll['totalVotes'] = $TotalVotes;
    $JsonPollAnswers = [];

    foreach ($Answers as $i => $Answer) {
        if (!empty($Votes[$i]) && $TotalVotes > 0) {
            $Ratio = $Votes[$i] / $MaxVotes;
            $Percent = $Votes[$i] / $TotalVotes;
        } else {
            $Ratio = 0;
            $Percent = 0;
        }
        $JsonPollAnswers[] = [
            'answer'  => $Answer,
            'ratio'   => $Ratio,
            'percent' => $Percent,
        ];
    }

    if ($UserResponse !== null || $Closed || $ThreadInfo['IsLocked'] || $LoggedUser['Class'] < $Forums[$ForumID]['MinClassWrite']) {
        $JsonPoll['voted'] = True;
    } else {
        $JsonPoll['voted'] = False;
    }

    $JsonPoll['answers'] = $JsonPollAnswers;
}

// Squeeze in stickypost
if ($ThreadInfo['StickyPostID']) {
    if ($ThreadInfo['StickyPostID'] != $Thread[0]['ID']) {
        array_unshift($Thread, $ThreadInfo['StickyPost']);
    }
    if ($ThreadInfo['StickyPostID'] != $Thread[count($Thread) - 1]['ID']) {
        $Thread[] = $ThreadInfo['StickyPost'];
    }
}

$JsonPosts = [];
foreach ($Thread as $Key => $Post) {
    list($PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime) = array_values($Post);
    list($AuthorID, $Username, $PermissionID, $Paranoia, $Artist, $Donor, $Warned, $Avatar, $Enabled, $UserTitle) = array_values(Users::user_info($AuthorID));

    $UserInfo = Users::user_info($EditedUserID);
    $JsonPosts[] = [
        'postId'         => (int)$PostID,
        'addedTime'      => $AddedTime,
        'bbBody'         => $Body,
        'body'           => Text::full_format($Body),
        'editedUserId'   => (int)$EditedUserID,
        'editedTime'     => $EditedTime,
        'editedUsername' => $UserInfo['Username'],
        'author' => [
            'authorId'   => (int)$AuthorID,
            'authorName' => $Username,
            'paranoia'   => $Paranoia,
            'artist'     => $Artist === '1',
            'donor'      => $Donor == 1,
            'warned'     => !is_null($Warned),
            'avatar'     => $Avatar,
            'enabled'    => $Enabled === '2' ? false : true,
            'userTitle'  => $UserTitle,
        ],
    ];
}

print json_encode([
    'status' => 'success',
    'response' => [
        'forumId'     => (int)$ForumID,
        'forumName'   => $Forums[$ForumID]['Name'],
        'threadId'    => (int)$ThreadID,
        'threadTitle' => display_str($ThreadInfo['Title']),
        'subscribed'  => in_array($ThreadID, $UserSubscriptions),
        'locked'      => $ThreadInfo['IsLocked'] == 1,
        'sticky'      => $ThreadInfo['IsSticky'] == 1,
        'currentPage' => (int)$Page,
        'pages'       => ceil($ThreadInfo['Posts'] / $PerPage),
        'poll'        => empty($JsonPoll) ? null : $JsonPoll,
        'posts'       => $JsonPosts,
    ]
]);
