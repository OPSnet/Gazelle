<?php

use Gazelle\Util\Time;

if (!$Viewer->permitted('users_warn')) {
    error(404);
}
foreach (['reason', 'privatemessage', 'body', 'length', 'postid'] as $var) {
    if (!isset($_POST[$var])) {
        error("$var not set");
    }
}

$comment = (new Gazelle\Manager\Comment)->findById((int)($_REQUEST['postid'] ?? 0));
if (is_null($comment)) {
    error(404);
}
$userMan = new Gazelle\Manager\User;
$user = $userMan->findById($comment->userId());
if (is_null($user) || $user->classLevel() > $Viewer->classLevel()) {
    error(403);
}

$comment->setBody(trim($_POST['body']))->modify();

$weeks   = trim($_POST['length']);
$reason  = trim($_POST['reason']);
$url     = $comment->publicUrl();
$context = trim($_POST['privatemessage']);
if ($weeks === 'verbal') {
    $subject = 'You have received a verbal warning';
    $body    = "You have received a verbal warning for [url=$url]this comment[/url].\n\n[quote]{$context}[/quote]";
    $note    = "Verbally warned by {$Viewer->username()}\nReason: $url - $reason";
    $user->addStaffNote($note);
} else {
    $weeks   = (int)$weeks;
    $expiry  = $userMan->warn($user, $weeks, "$url - $reason", $Viewer);
    $subject = 'You have received a warning';
    $body    = "You have received a $weeks week warning for [url=$url]this comment[/url].\n\n[quote]{$context}[/quote]";
    $note    = "Warned until $expiry by {$Viewer->username()}\nReason: $url - $reason";
}
$user->addForumWarning($note)->modify();
$userMan->sendPM($user->id(), $Viewer->id(), $subject, $body);

header("Location: $url");
