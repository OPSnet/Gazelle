<?php

if (!$Viewer->permitted('users_warn')) {
    error(403);
}
if (empty($_POST['reason'])) {
    error("Reason for warning not provided");
}
if (empty($_POST['body'])) {
    error("Post body cannot be left empty (you can leave a reason for others to see)");
}
if (empty($_POST['length'])) {
    error("Length of warning not provided");
}

$userMan = new Gazelle\Manager\User;
$user = $userMan->findById((int)$_POST['userid']);
if (is_null($user)) {
    error("No user ID given");
}
if ($user->primaryClass() > $Viewer->classLevel()) {
    error(403);
}

$forum = new Gazelle\Forum((int)($_POST['forumid']));
$postId = (int)($_POST['postid'] ?? 0);
$forumPost = $forum->postInfo($postId);
if (empty($forumPost)) {
    error("No forum post #$postId found");
}
$threadId = $forumPost['thread-id'];
if (empty($forum->threadInfo($threadId))) {
    error(404);
}
$forum->editPost($user->id(), $postId, trim($_POST['body']));

$URL = "forums.php?action=viewthread&amp;postid=$postId#post$postId";
$Reason = trim($_POST['reason']);
$WarningLength = $_POST['length'];
if ($WarningLength !== 'verbal') {
    $Time = (int)$WarningLength * (7 * 24 * 60 * 60);
    $userMan->warn($user->id(), $Time, "$URL - $Reason", $Viewer->username());
    $subject = 'You have received a warning';
    $message = "You have received a $WarningLength week warning for [url=$URL]this post[/url].";
    $warned = "Warned until " . time_plus($Time);
} else {
    $subject = 'You have received a verbal warning';
    $message = "You have received a verbal warning for [url=$URL]this post[/url].";
    $warned = "Verbally warned";
}
$adminComment = date('Y-m-d') . " - $warned by " . $Viewer->username() . " for $URL\nReason: $Reason";

$extraMessage = trim($_POST['privatemessage'] ?? '');
if (strlen($extraMessage)) {
    $message .= "\n\n$extraMessage";
}

$user->addForumWarning($adminComment)->addStaffNote($adminComment)->modify();
$userMan->sendPM($user->id(), $Viewer->id(), $subject, $message);

if ($forumPost['is-sticky']) {
    $Cache->delete_value("thread_{$threadId}_info");
}

$Cache->delete_value("thread_{$threadId}_catalogue_"
    . (int)floor((POSTS_PER_PAGE * $forumPost['page'] - POSTS_PER_PAGE) / THREAD_CATALOGUE)
);

header("Location: forums.php?action=viewthread&postid=$postId#post$postId");
