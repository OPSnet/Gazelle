<?php

authorize();

if (empty($_POST['groupid']) || !is_numeric($_POST['groupid'])) {
    error(403);
}

$EditForumID = EDITING_FORUM_ID;
$BotID = SYSTEM_USER_ID;

$GroupID = intval($_POST['groupid']);

include(SERVER_ROOT.'/sections/torrents/functions.php');
$TorrentCache = get_group_info($GroupID, $RevisionID);

$TorrentDetails = $TorrentCache[0];
$TorrentList = $TorrentCache[1];

// Group details
list($WikiBody, $WikiImage, $GroupID, $GroupName, $GroupYear,
    $GroupRecordLabel, $GroupCatalogueNumber, $ReleaseType, $GroupCategoryID,
    $GroupTime, $GroupVanityHouse, $TorrentTags, $TorrentTagIDs, $TorrentTagUserIDs,
    $TagPositiveVotes, $TagNegativeVotes, $GroupFlags) = array_values($TorrentDetails);

$Title = $GroupName;
$Artists = Artists::get_artist($GroupID);

if ($Artists) {
    $GroupName = Artists::display_artists($Artists, false, true, false) . $GroupName;
}

if ($GroupYear > 0) {
    $GroupName .= ' ['.$GroupYear.']';
}

if ($GroupVanityHouse) {
    $GroupName .= ' [Vanity House]';
}

$sqltime = sqltime();
$UserLink = site_url().'user.php?id='.G::$LoggedUser['ID'];
$Username = G::$LoggedUser['Username'];
$TorrentLink = site_url().'torrents.php?id='.$GroupID;
$Title = 'Torrent Group: ' . $GroupName;
$Body = <<<POST
[url={$UserLink}]{$Username}[/url] has submitted an editing request for: [url={$TorrentLink}]{$GroupName}[/url]

[quote=Comments]{$_POST['edit_details']}[/quote]
POST;

$forum = new \Gazelle\Forum($EditForumID);
list ($TopicID, $postId) = $forum->addThread($BotID, $Title, $Body);

// if cache exists modify it, if not, then it will be correct when selected next, and we can skip this block
if ($Forum = $Cache->get_value("forums_{$EditForumID}")) {
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
    $Part2 = [$TopicID => [
        'ID' => $TopicID,
        'Title' => $Title,
        'AuthorID' => $BotID,
        'IsLocked' => 0,
        'IsSticky' => 0,
        'NumPosts' => 1,
        'LastPostID' => $postId,
        'LastPostTime' => $sqltime,
        'LastPostAuthorID' => $BotID,
        'NoPoll' => 1
    ]]; // Bumped
    $Forum = $Part1 + $Part2 + $Part3;

    $Cache->cache_value("forums_{$EditForumID}", [$Forum, '', 0, $Stickies], 0);

    // Update the forum root
    $Cache->begin_transaction('forums_list');
    $Cache->update_row($EditForumID, [
        'NumPosts' => '+1',
        'NumTopics' => '+1',
        'LastPostID' => $postId,
        'LastPostAuthorID' => $BotID,
        'LastPostTopicID' => $TopicID,
        'LastPostTime' => $sqltime,
        'Title' => $Title,
        'IsLocked' => 0,
        'IsSticky' => 0
    ]);
    $Cache->commit_transaction(0);
}
else {
    // If there's no cache, we have no data, and if there's no data
    $Cache->delete_value('forums_list');
}

$Cache->begin_transaction("thread_{$TopicID}_catalogue_0");
$Post = [
    'ID' => $postId,
    'AuthorID' => $BotID,
    'AddedTime' => $sqltime,
    'Body' => $Body,
    'EditedUserID' => 0,
    'EditedTime' => null,
];
$Cache->insert('', $Post);
$Cache->commit_transaction(0);

$Cache->begin_transaction("thread_{$TopicID}_info");
$Cache->update_row(false, [
    'Posts' => '+1',
    'LastPostAuthorID' => $BotID,
    'LastPostTime' => $sqltime
]);
$Cache->commit_transaction(0);

header("Location: forums.php?action=viewthread&threadid={$TopicID}");
