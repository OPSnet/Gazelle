<?php

authorize();

if (empty($_POST['artistid']) || !is_numeric($_POST['artistid'])) {
    error(403);
}

$EditForumID = EDITING_FORUM_ID;
$BotID = SYSTEM_USER_ID;

$ArtistID = intval($_POST['artistid']);

$DB->prepared_query("SELECT
            Name,
            VanityHouse
        FROM artists_group
        WHERE ArtistID = ?", $ArtistID);

if (!$DB->has_results()) {
    error(404);
}

list($Name, $VanityHouseArtist) = $DB->fetch_record();

if ($VanityHouseArtist) {
    $Name .= ' [Vanity House]';
}

$sqltime = sqltime();
$UserLink = site_url().'user.php?id='.G::$LoggedUser['ID'];
$Username = G::$LoggedUser['Username'];
$ArtistLink = site_url().'artist.php?id='.$ArtistID;
$Title = 'Artist: ' . $Name;
$Body = <<<POST
[url={$UserLink}]{$Username}[/url] has submitted an editing request for: [url={$ArtistLink}]{$Name}[/url]

[quote=Comments]{$_POST['edit_details']}[/quote]
POST;

$forum = new \Gazelle\Forum($EditForumID);
list ($threadId, $postId) = $forum->addThread($BotID, $Title, $Body);

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
    $Part2 = [$threadId => [
        'ID'               => $threadId,
        'Title'            => $Title,
        'AuthorID'         => $BotID,
        'IsLocked'         => 0,
        'IsSticky'         => 0,
        'NumPosts'         => 1,
        'LastPostID'       => $postId,
        'LastPostTime'     => $sqltime,
        'LastPostAuthorID' => $BotID,
        'NoPoll'           => 1
    ]]; // Bumped
    $Forum = $Part1 + $Part2 + $Part3;

    $Cache->cache_value("forums_{$EditForumID}", [$Forum, '', 0, $Stickies], 0);

    // Update the forum root
    $Cache->begin_transaction('forums_list');
    $Cache->update_row($EditForumID, [
        'NumPosts'         => '+1',
        'NumTopics'        => '+1',
        'LastPostID'       => $postId,
        'LastPostAuthorID' => $BotID,
        'LastPostTopicID'  => $threadId,
        'LastPostTime'     => $sqltime,
        'Title'            => $Title,
        'IsLocked'         => 0,
        'IsSticky'         => 0
    ]);
    $Cache->commit_transaction(0);
}
else {
    // If there's no cache, we have no data, and if there's no data
    $Cache->delete_value('forums_list');
}

$Cache->begin_transaction("thread_{$threadId}_catalogue_0");
$Post = [
    'ID'           => $postId,
    'AuthorID'     => $BotID,
    'AddedTime'    => $sqltime,
    'Body'         => $Body,
    'EditedUserID' => 0,
    'EditedTime'   => null,
];
$Cache->insert('', $Post);
$Cache->commit_transaction(0);

$Cache->begin_transaction("thread_{$threadId}_info");
$Cache->update_row(false, [
    'Posts'            => '+1',
    'LastPostAuthorID' => $BotID,
    'LastPostTime'     => $sqltime
]);
$Cache->commit_transaction(0);

header("Location: forums.php?action=viewthread&threadid={$threadId}");
