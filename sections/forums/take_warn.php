<?php
if (!check_perms('users_warn')) {
    error(404);
}
Misc::assert_isset_request($_POST, ['reason', 'privatemessage', 'body', 'length', 'postid', 'userid']);

$userId = (int)$_POST['userid'];
$UserInfo = Users::user_info($userId);
if ($UserInfo['Class'] > $LoggedUser['Class']) {
    error(403);
}

$Reason = $_POST['reason'];
$PrivateMessage = $_POST['privatemessage'];
$Body = $_POST['body'];
$WarningLength = $_POST['length'];
$forumId = (int)$_POST['forumid'];
$postId = (int)$_POST['postid'];
$Key = (int)$_POST['key'];
$URL = site_url() . "forums.php?action=viewthread&amp;postid=$postId#post$postId";
if ($WarningLength !== 'verbal') {
    $Time = (int)$WarningLength * (7 * 24 * 60 * 60);
    Tools::warn_user($userId, $Time, "$URL - $Reason");
    $Subject = 'You have received a warning';
    $PrivateMessage = "You have received a $WarningLength week warning for [url=$URL]this post[/url].\n\n" . $PrivateMessage;

    $WarnTime = time_plus($Time);
    $AdminComment = date('Y-m-d') . " - Warned until $WarnTime by " . $LoggedUser['Username'] . " for $URL\nReason: $Reason\n\n";

} else {
    $Subject = 'You have received a verbal warning';
    $PrivateMessage = "You have received a verbal warning for [url=$URL]this post[/url].\n\n" . $PrivateMessage;
    $AdminComment = date('Y-m-d') . ' - Verbally warned by ' . $LoggedUser['Username'] . " for $URL\nReason: $Reason\n\n";
    Tools::update_user_notes($userId, $AdminComment);
}

$user = new \Gazelle\User($userId);
$user->addForumWarning($AdminComment);

Misc::send_pm($userId, $LoggedUser['ID'], $Subject, $PrivateMessage);

$forum = new \Gazelle\Forum($forumId);
$forum->editPost($userId, $postId, $Body);

$forumPost = $forum->postInfo($postId);
$threadId = $forumPost['thread-id'];

$ThreadInfo = Forums::get_thread_info($threadId);
if ($ThreadInfo === null) {
    error(404);
}
if ($forumPost['is-sticky']) {
    $ThreadInfo['StickyPost'] = [
        'Body'         => $Body,
        'EditedUserID' => $LoggedUser['ID'],
        'EditedTime'   => sqltime(),
    ];
    $Cache->cache_value("thread_{$threadId}_info", $ThreadInfo, 0);
}

$Cache->delete_value("thread_{$threadId}_catalogue_"
    . (int)floor((POSTS_PER_PAGE * $forumPost['page'] - POSTS_PER_PAGE) / THREAD_CATALOGUE)
);

header("Location: forums.php?action=viewthread&postid=$postId#post$postId");
