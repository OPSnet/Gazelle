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

$DB->prepared_query("
    INSERT INTO forums_topics
        (Title, AuthorID, ForumID, LastPostTime, LastPostAuthorID, CreatedTime)
    Values
        (?, ?, ?, ?, ?, ?)", $Title, $BotID, $EditForumID, $sqltime, $BotID, $sqltime);
$TopicID = $DB->inserted_id();

$DB->prepared_query("
    INSERT INTO forums_posts
        (TopicID, AuthorID, AddedTime, Body)
    VALUES
        (?, ?, ?, ?)", $TopicID, $BotID, $sqltime, $Body);

$PostID = $DB->inserted_id();

$DB->prepared_query("
    UPDATE forums
    SET
        NumPosts         = NumPosts + 1,
        NumTopics        = NumTopics + 1,
        LastPostID       = ?,
        LastPostAuthorID = ?,
        LastPostTopicID  = ?,
        LastPostTime     = ?
    WHERE ID = ?", $PostID, $BotID, $TopicID, $sqltime, $EditForumID);

$DB->prepared_query("
    UPDATE forums_topics
    SET
        NumPosts         = NumPosts + 1,
        LastPostID       = ?,
        LastPostAuthorID = ?,
        LastPostTime     = ?
    WHERE ID = ?", $PostID, $BotID, $sqltime, $TopicID);

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
    $Part2 = array($TopicID => array(
        'ID' => $TopicID,
        'Title' => $Title,
        'AuthorID' => $BotID,
        'IsLocked' => 0,
        'IsSticky' => 0,
        'NumPosts' => 1,
        'LastPostID' => $PostID,
        'LastPostTime' => $sqltime,
        'LastPostAuthorID' => $BotID,
        'NoPoll' => 1
    )); // Bumped
    $Forum = $Part1 + $Part2 + $Part3;

    $Cache->cache_value("forums_{$EditForumID}", array($Forum, '', 0, $Stickies), 0);

    // Update the forum root
    $Cache->begin_transaction('forums_list');
    $Cache->update_row($EditForumID, array(
        'NumPosts' => '+1',
        'NumTopics' => '+1',
        'LastPostID' => $PostID,
        'LastPostAuthorID' => $BotID,
        'LastPostTopicID' => $TopicID,
        'LastPostTime' => $sqltime,
        'Title' => $Title,
        'IsLocked' => 0,
        'IsSticky' => 0
    ));
    $Cache->commit_transaction(0);
}
else {
    // If there's no cache, we have no data, and if there's no data
    $Cache->delete_value('forums_list');
}

$Cache->begin_transaction("thread_{$TopicID}_catalogue_0");
$Post = [
    'ID' => $PostID,
    'AuthorID' => $BotID,
    'AddedTime' => $sqltime,
    'Body' => $Body,
    'EditedUserID' => 0,
    'EditedTime' => '0000-00-00 00:00:00'
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
