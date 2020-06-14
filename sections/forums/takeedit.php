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
$DoPM    = isset($_POST['pm']) ? $_POST['pm'] : 0;

$forum = new \Gazelle\Forum();
$forumPost = $forum->postInfo($PostID);
$TopicID = $forumPost['thread-id'];

if (!Forums::check_forumperm($ForumID, 'Write')) {
    error('You lack the permission to edit this post.', true);
}
if ($forumPost['is-locked'] && !check_perms('site_moderate_forums')) {
    error('You cannot edit a locked post.', true);
}
if ($UserID != $forumPost['user-id'] && !check_perms('site_moderate_forums')) {
    error(403, true);
}

// Send a PM to the user to notify them of the edit
if ($UserID != $forumPost['user-id'] && $DoPM) {
    Misc::send_pm(
        $forumPost['user-id'], 0,
        "Your post #$PostID has been edited",
        sprintf('One of your posts has been edited by %s: [url]%s[/url]',
            '[url='.site_url()."user.php?id=$UserID]".$LoggedUser['Username'].'[/url]',
            site_url()."forums.php?action=viewthread&postid=$PostID#post$PostID"
        )
    );
}

$forum->editPost($UserID, $PostID, $Body);

$Cache->deleteMulti([
    "thread_{$TopicID}_catalogue_" . (int)floor((POSTS_PER_PAGE * $forumPost['page'] - POSTS_PER_PAGE) / THREAD_CATALOGUE),
    "thread_{$TopicID}_info",
]);

// This gets sent to the browser, which echoes it in place of the old body
echo Text::full_format($Body);
?>
<br /><br /><span class="last_edited">Last edited by <a href="user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> Just now</span>
