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

$SQLTime = sqltime();

$Body = trim($_POST['body']);

$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
if (isset($_POST['subscribe']) && !$subscription->isSubscribed($TopicID)) {
    $subscription->subscribe($TopicID);
}

$forum = new \Gazelle\Forum($ForumID);

// Handle the special case of merging posts, we can skip bumping the thread and all that fun
if ($ThreadInfo['LastPostAuthorID'] == $LoggedUser['ID'] && isset($_POST['merge'])) {
    $PostID = $forum->mergePost($LoggedUser['ID'], $TopicID, $Body);

    //Get the catalogue it is in
    $CatalogueID = floor((POSTS_PER_PAGE * ceil($ThreadInfo['Posts'] / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE);

    //Get the catalogue value for the post we're appending to
    if ($ThreadInfo['Posts'] % THREAD_CATALOGUE == 0) {
        $Key = THREAD_CATALOGUE - 1;
    } else {
        $Key = ($ThreadInfo['Posts'] % THREAD_CATALOGUE) - 1;
    }
    if ($ThreadInfo['StickyPostID'] == $PostID) {
        $ThreadInfo['StickyPost']['Body'] .= "\n\n$Body";
        $ThreadInfo['StickyPost']['EditedUserID'] = $LoggedUser['ID'];
        $ThreadInfo['StickyPost']['EditedTime'] = $SQLTime;
        $Cache->cache_value("thread_$TopicID".'_info', $ThreadInfo, 0);
    }

    //Edit the post in the cache
    $Cache->begin_transaction("thread_$TopicID"."_catalogue_$CatalogueID");
    $Cache->update_row($Key, [
        'Body'         => $Cache->MemcacheDBArray[$Key]['Body']."\n\n$Body",
        'EditedUserID' => $LoggedUser['ID'],
        'EditedTime'   => $SQLTime,
        'Username'     => $LoggedUser['Username']
    ]);
    $Cache->commit_transaction(0);

// We're dealing with a normal post
} else {
    //Insert the post into the posts database
    $PostID = $forum->addPost($LoggedUser['ID'], $TopicID, $Body);

    // if cache exists modify it, if not, then it will be correct when selected next, and we can skip this block
    if ($Forum = $Cache->get_value("forums_$ForumID")) {
        list($Forum,,,$Stickies) = $Forum;

        // if the topic is already on this page
        if (array_key_exists($TopicID, $Forum)) {
            $Thread = $Forum[$TopicID];
            unset($Forum[$TopicID]);
            $Thread['NumPosts'] = $Thread['NumPosts'] + 1; // Increment post count
            $Thread['LastPostID'] = $PostID; // Set post ID for read/unread
            $Thread['LastPostTime'] = $SQLTime; // Time of last post
            $Thread['LastPostAuthorID'] = $LoggedUser['ID']; // Last poster ID
            $Part2 = [$TopicID => $Thread]; // Bumped thread

        // if we're bumping from an older page
        } else {
            // Remove the last thread from the index
            if (count($Forum) == TOPICS_PER_PAGE && $Stickies < TOPICS_PER_PAGE) {
                array_pop($Forum);
            }
            // Never know if we get a page full of stickies...
            if ($Stickies < TOPICS_PER_PAGE || $ThreadInfo['IsSticky'] == 1) {
                //Pull the data for the thread we're bumping
                list($AuthorID, $IsLocked, $IsSticky, $NumPosts, $NoPoll) = $forum->threadInfo($TopicID);
                $Part2 = [$TopicID => [
                    'ID'               => $TopicID,
                    'Title'            => $ThreadInfo['Title'],
                    'AuthorID'         => $AuthorID,
                    'IsLocked'         => $IsLocked,
                    'IsSticky'         => $IsSticky,
                    'NumPosts'         => $NumPosts,
                    'LastPostID'       => $PostID,
                    'LastPostTime'     => $SQLTime,
                    'LastPostAuthorID' => $LoggedUser['ID'],
                    'NoPoll'           => $NoPoll
                ]]; //Bumped
            } else {
                $Part2 = [];
            }
        }
        if ($Stickies > 0) {
            $Part1 = array_slice($Forum, 0, $Stickies, true); //Stickies
            $Part3 = array_slice($Forum, $Stickies, TOPICS_PER_PAGE - $Stickies - 1, true); //Rest of page
        } else {
            $Part1 = [];
            $Part3 = $Forum;
        }
        if (is_null($Part1)) {
            $Part1 = [];
        }
        if (is_null($Part3)) {
            $Part3 = [];
        }
        if ($ThreadInfo['IsSticky'] == 1) {
            $Forum = $Part2 + $Part1 + $Part3; //Merge it
        } else {
            $Forum = $Part1 + $Part2 + $Part3; //Merge it
        }
        $Cache->cache_value("forums_$ForumID", [$Forum, '', 0, $Stickies], 0);

        //Update the forum root
        $Cache->begin_transaction('forums_list');
        $Cache->update_row($ForumID, [
            'NumPosts'         => '+1',
            'LastPostID'       => $PostID,
            'LastPostAuthorID' => $LoggedUser['ID'],
            'LastPostTopicID'  => $TopicID,
            'LastPostTime'     => $SQLTime,
            'Title'            => $ThreadInfo['Title'],
            'IsLocked'         => $ThreadInfo['IsLocked'],
            'IsSticky'         => $ThreadInfo['IsSticky']
        ]);
        $Cache->commit_transaction(0);
    } else {
        //If there's no cache, we have no data, and if there's no data
        $Cache->delete_value('forums_list');
    }

    //This calculates the block of 500 posts that this one will fall under
    $CatalogueID = floor((POSTS_PER_PAGE * ceil($ThreadInfo['Posts'] / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE);

    //Insert the post into the thread catalogue (block of 500 posts)
    $Cache->begin_transaction("thread_$TopicID"."_catalogue_$CatalogueID");
    $Cache->insert('', [
        'ID'           => $PostID,
        'AuthorID'     => $LoggedUser['ID'],
        'AddedTime'    => $SQLTime,
        'Body'         => $Body,
        'EditedUserID' => 0,
        'EditedTime'   => null,
        'Username'     => $LoggedUser['Username'] //TODO: Remove, it's never used?
        ]);
    $Cache->commit_transaction(0);

    //Update the thread info
    $Cache->begin_transaction("thread_$TopicID".'_info');
    $Cache->update_row(false, ['Posts' => '+1', 'LastPostAuthorID' => $LoggedUser['ID'], 'LastPostTime' => $SQLTime]);
    $Cache->commit_transaction(0);

    //Increment this now to make sure we redirect to the correct page
    $ThreadInfo['Posts']++;
}

$subscription->flush('forums', $TopicID);
$subscription->quoteNotify($Body, $PostID, 'forums', $TopicID);

if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

header("Location: forums.php?action=viewthread&threadid=$TopicID&page=".ceil($ThreadInfo['Posts'] / $PerPage));
