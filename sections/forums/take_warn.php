<?php

use Gazelle\Util\Time;

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

$reason = trim($_POST['reason']);
$weeks = $_POST['length'];
if ($weeks === 'verbal') {
    $subject = 'You have received a verbal warning';
    $message = "You have received a verbal warning for [url={$post->url()}]this post[/url].";
    $warned = "Verbally warned";
} else {
    $expiry = $userMan->warn($user, $weeks, "{$post->url()} - $reason", $Viewer);
    $subject = 'You have received a warning';
    $message = "You have received a $weeks week warning for [url={$post->url()}]this post[/url].";
    $warned = "Warned until $expiry";
}

$extraMessage = trim($_POST['privatemessage'] ?? '');
if (strlen($extraMessage)) {
    $message .= "\n\n$extraMessage";
}

$user->addForumWarning(date('Y-m-d') . " - $warned by {$Viewer->username()} for {$post->url()}\nReason: $reason")
    ->modify();

if ($post->isPinned()) {
    $post->thread()->flush();
}

header("Location: {$post->location()}");
