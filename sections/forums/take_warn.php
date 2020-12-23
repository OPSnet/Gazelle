<?php
if (!check_perms('users_warn')) {
    error(404);
}
Misc::assert_isset_request($_POST, ['reason', 'privatemessage', 'body', 'length', 'postid', 'userid']);

$userMan = new Gazelle\Manager\User;
$user = $userMan->findById((int)$_POST['userid']);
if (is_null($user)) {
    error(404);
}
if ($user->primaryClass() > $LoggedUser['Class']) {
    error(403);
}

$forumId = (int)$_POST['forumid'];
$postId = (int)$_POST['postid'];

$forum = new Gazelle\Forum($forumId);
$forumPost = $forum->postInfo($postId);
$threadId = $forumPost['thread-id'];
if (Forums::get_thread_info($threadId) === null) {
    error(404);
}

$URL = SITE_URL . "/forums.php?action=viewthread&amp;postid=$postId#post$postId";
$Reason = trim($_POST['reason']);
$PrivateMessage = trim($_POST['privatemessage']);
$Body = trim($_POST['body']);
$forum->editPost($user->id(), $postId, $Body);

$WarningLength = $_POST['length'];
if ($WarningLength !== 'verbal') {
    $Time = (int)$WarningLength * (7 * 24 * 60 * 60);
    Tools::warn_user($user->id(), $Time, "$URL - $Reason");
    $Subject = 'You have received a warning';
    $PrivateMessage = "You have received a $WarningLength week warning for [url=$URL]this post[/url].\n\n" . $PrivateMessage;

    $WarnTime = time_plus($Time);
    $AdminComment = date('Y-m-d') . " - Warned until $WarnTime by " . $LoggedUser['Username'] . " for $URL\nReason: $Reason";

} else {
    $Subject = 'You have received a verbal warning';
    $PrivateMessage = "You have received a verbal warning for [url=$URL]this post[/url].\n\n" . $PrivateMessage;
    $AdminComment = date('Y-m-d') . ' - Verbally warned by ' . $LoggedUser['Username'] . " for $URL\nReason: $Reason";
}

$user->addForumWarning($AdminComment)->addStaffNote($AdminComment)->modify();
$userMan->sendPM($user->id(), $LoggedUser['ID'], $Subject, $PrivateMessage);

if ($forumPost['is-sticky']) {
    $Cache->delete_value("thread_{$threadId}_info");
}

$Cache->delete_value("thread_{$threadId}_catalogue_"
    . (int)floor((POSTS_PER_PAGE * $forumPost['page'] - POSTS_PER_PAGE) / THREAD_CATALOGUE)
);

header("Location: forums.php?action=viewthread&postid=$postId#post$postId");
