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

try {
    $comment = (new Gazelle\Manager\Comment)->findById((int)($_REQUEST['postid'] ?? 0));
} catch (\Gazelle\Exception\ResourceNotFoundException $e) {
    error(404);
}
$user = new Gazelle\User($comment->userId());
if ($user->classLevel() > $Viewer->classLevel()) {
    error(403);
}
$userMan = new Gazelle\Manager\User;

$url = SITE_URL . '/' . $comment->url();
$comment->setBody(trim($_POST['body']))->modify();

$Length = trim($_POST['length']);
$Reason = trim($_POST['reason']);
$PrivateMessage = trim($_POST['privatemessage']);
if ($Length !== 'verbal') {
    $Time = (int)$Length * (7 * 24 * 60 * 60);
    $WarnTime = Time::timePlus($Time);
    $userMan->warn($user->id(), $Time, "$url - $Reason", $Viewer->username());
    $subject = 'You have received a warning';
    $message = "You have received a $Length week warning for [url=$url]this comment[/url].\n\n[quote]{$PrivateMessage}[/quote]";
    $note = "Warned until $WarnTime by " . $Viewer->username() . "\nReason: $url - $Reason";
} else {
    $subject = 'You have received a verbal warning';
    $message = "You have received a verbal warning for [url=$url]this comment[/url].\n\n[quote]{$PrivateMessage}[/quote]";
    $note = "Verbally warned by " . $Viewer->username() . "\nReason: $url - $Reason";
    $user->addStaffNote($note);
}
$user->addForumWarning($note)->modify();
$userMan->sendPM($user->id(), $Viewer->id(), $subject, $message);

header("Location: $url");
