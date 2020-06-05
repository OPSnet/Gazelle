<?php
authorize();

/*********************************************************************\
//--------------Take Post--------------------------------------------//

The page that handles the backend of the 'edit post' function.

$_GET['action'] must be "takeedit" for this page to work.

It will be accompanied with:
    $_POST['post'] - the ID of the post
    $_POST['body']


\*********************************************************************/

// Quick SQL injection check
if (!$_POST['post'] || !is_number($_POST['post']) || !is_number($_POST['key'])) {
    error(0,true);
}
// End injection check

if ($LoggedUser['DisablePosting']) {
    error('Your posting privileges have been removed.');
}

// Variables for database input
$UserID  = $LoggedUser['ID'];
$Body    = $_POST['body']; //Don't URL Decode
$ForumID = $_POST['forumid'];
$PostID  = $_POST['post'];
$Key     = $_POST['key'];
$SQLTime = sqltime();
$DoPM    = isset($_POST['pm']) ? $_POST['pm'] : 0;

$forum = new \Gazelle\Forum(0);
list($OldBody, $AuthorID, $TopicID, $ForumID, $IsLocked, $MinClassWrite, $Page) = $forum->postInfo($PostID);
$forum->setForum($ForumID);

// Make sure they aren't trying to edit posts they shouldn't
// We use die() here instead of error() because whatever we spit out is displayed to the user in the box where his forum post is
if (!Forums::check_forumperm($ForumID, 'Write') || ($IsLocked && !check_perms('site_moderate_forums'))) {
    error('Either the thread is locked, or you lack the permission to edit this post.', true);
}
if ($UserID != $AuthorID && !check_perms('site_moderate_forums')) {
    error(403,true);
}
if (!$DB->has_results()) {
    error(404,true);
}

// Send a PM to the user to notify them of the edit
if ($UserID != $AuthorID && $DoPM) {
    $PMSubject = "Your post #$PostID has been edited";
    $PMurl     = site_url()."forums.php?action=viewthread&postid=$PostID#post$PostID";
    $ProfLink  = '[url='.site_url()."user.php?id=$UserID]".$LoggedUser['Username'].'[/url]';
    $PMBody    = "One of your posts has been edited by $ProfLink: [url]{$PMurl}[/url]";
    Misc::send_pm($AuthorID, 0, $PMSubject, $PMBody);
}

// Perform the update
$forum->editPost($UserID, $PostID, $Body);
$forum->saveEdit($UserID, $PostID, $OldBody);

$CatalogueID = floor((POSTS_PER_PAGE * $Page - POSTS_PER_PAGE) / THREAD_CATALOGUE);
$Cache->begin_transaction("thread_$TopicID"."_catalogue_$CatalogueID");
if ($Cache->MemcacheDBArray[$Key]['ID'] != $PostID) {
    $Cache->cancel_transaction();
    $Cache->delete_value("thread_$TopicID"."_catalogue_$CatalogueID"); //just clear the cache for would be cache-screwer-uppers
} else {
    $Cache->update_row($Key, [
        'ID'           => $Cache->MemcacheDBArray[$Key]['ID'],
        'AuthorID'     => $Cache->MemcacheDBArray[$Key]['AuthorID'],
        'AddedTime'    => $Cache->MemcacheDBArray[$Key]['AddedTime'],
        'Body'         => $Body, //Don't url decode.
        'EditedUserID' => $LoggedUser['ID'],
        'EditedTime'   => $SQLTime,
        'Username'     => $LoggedUser['Username']
    ]);
    $Cache->commit_transaction(3600 * 24 * 5);
}
$ThreadInfo = Forums::get_thread_info($TopicID);
if ($ThreadInfo === null) {
    error(404);
}
if ($ThreadInfo['StickyPostID'] == $PostID) {
    $ThreadInfo['StickyPost']['Body'] = $Body;
    $ThreadInfo['StickyPost']['EditedUserID'] = $LoggedUser['ID'];
    $ThreadInfo['StickyPost']['EditedTime'] = $SQLTime;
    $Cache->cache_value("thread_$TopicID".'_info', $ThreadInfo, 0);
}

// This gets sent to the browser, which echoes it in place of the old body
echo Text::full_format($Body);
?>
<br /><br /><span class="last_edited">Last edited by <a href="user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> Just now</span>
