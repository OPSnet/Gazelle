<?php
if (!check_perms('users_warn')) {
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
if ($user->classLevel() > $LoggedUser['Class']) {
    error(403);
}

$url = SITE_URL . '/' . $comment->url();
$comment->setBody(trim($_POST['body']))->modify();

$Length = trim($_POST['length']);
$Reason = trim($_POST['reason']);
$PrivateMessage = trim($_POST['privatemessage']);
if ($Length !== 'verbal') {
    $Time = (int)$Length * (7 * 24 * 60 * 60);
    $WarnTime = time_plus($Time);
    Tools::warn_user($user->id(), $Time, "$url - $Reason");
    $Subject = 'You have received a warning';
    $PrivateMessage = "You have received a $Length week warning for [url=$url]this comment[/url].\n\n[quote]{$PrivateMessage}[/quote]";
    $AdminComment = "Warned until $WarnTime by {$LoggedUser['Username']}\nReason: $url - $Reason";
} else {
    $Subject = 'You have received a verbal warning';
    $PrivateMessage = "You have received a verbal warning for [url=$url]this comment[/url].\n\n[quote]{$PrivateMessage}[/quote]";
    $AdminComment = "Verbally warned by {$LoggedUser['Username']}\nReason: $url - $Reason";
    $user->addStaffNote($AdminComment);
}
$user->addForumWarning($AdminComment)->modify();
(new \Gazelle\Manager\User)->sendPM($user->id(), $LoggedUser['ID'], $Subject, $PrivateMessage);

header("Location: $url");
