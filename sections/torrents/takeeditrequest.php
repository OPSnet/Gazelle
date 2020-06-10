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

header("Location: forums.php?action=viewthread&threadid={$TopicID}");
