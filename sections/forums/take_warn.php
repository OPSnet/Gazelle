<?php

if (!$Viewer->permitted('users_warn')) {
    error(403);
}

$body = trim($_POST['body'] ?? '');
if (empty($body)) {
    error("Post body cannot be left empty (you can leave a reason for others to see)");
}

if (empty($_POST['reason'])) {
    error("Reason for warning not provided");
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

$postId = (int)($_POST['postid'] ?? 0);
$post = (new Gazelle\Manager\ForumPost)->findById($postId);
if (is_null($post)) {
    error("No forum post #$postId found");
}
$post->setUpdate('Body', $body)->modify();

$Reason = trim($_POST['reason']);
$WarningLength = $_POST['length'];
if ($WarningLength !== 'verbal') {
    $Time = (int)$WarningLength * (7 * 24 * 60 * 60);
    $userMan->warn($user->id(), $Time, "{$post->url()} - $Reason", $Viewer->username());
    $subject = 'You have received a warning';
    $message = "You have received a $WarningLength week warning for [url={$post->url()}]this post[/url].";
    $warned = "Warned until " . time_plus($Time);
} else {
    $subject = 'You have received a verbal warning';
    $message = "You have received a verbal warning for [url={$post->url()}]this post[/url].";
    $warned = "Verbally warned";
}
$adminComment = date('Y-m-d') . " - $warned by " . $Viewer->username() . " for {$post->url()}\nReason: $Reason";

$extraMessage = trim($_POST['privatemessage'] ?? '');
if (strlen($extraMessage)) {
    $message .= "\n\n$extraMessage";
}

$user->addForumWarning($adminComment)->addStaffNote($adminComment)->modify();
$userMan->sendPM($user->id(), $Viewer->id(), $subject, $message);

if ($post->isPinned()) {
    $thread->flush();
}

header("Location: {$post->location()}");
