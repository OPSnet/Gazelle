<?php
if (!check_perms('users_warn')) {
    error(404);
}
foreach (['reason', 'privatemessage', 'body', 'length', 'postid'] as $var) {
    if (!isset($_POST[$var])) {
        error("$var not set");
    }
}

$postId = (int)$_POST['postid'];
$AuthorID = $DB->scalar("
    SELECT AuthorID FROM comments WHERE ID = ?
    ", $postId
);
if (!$AuthorID) {
    error(404);
}
$user = new Gazelle\User($AuthorID);

if ($user->classLevel() > $LoggedUser['Class']) {
    error(403);
}

$Reason = trim($_POST['reason']);
$PrivateMessage = trim($_POST['privatemessage']);
$Body = trim($_POST['body']);
$Length = trim($_POST['length']);

$URL = SITE_URL . '/' . Comments::get_url_query($postId);

if ($Length !== 'verbal') {
    $Time = (int)$Length * (7 * 24 * 60 * 60);
    $WarnTime = time_plus($Time);
    Tools::warn_user($AuthorID, $Time, "$URL - $Reason");
    $Subject = 'You have received a warning';
    $PrivateMessage = "You have received a $Length week warning for [url=$URL]this comment[/url].\n\n[quote]{$PrivateMessage}[/quote]";
    $AdminComment = "Warned until $WarnTime by {$LoggedUser['Username']}\nReason: $URL - $Reason";
} else {
    $Subject = 'You have received a verbal warning';
    $PrivateMessage = "You have received a verbal warning for [url=$URL]this comment[/url].\n\n[quote]{$PrivateMessage}[/quote]";
    $AdminComment = "Verbally warned by {$LoggedUser['Username']}\nReason: $URL - $Reason";
    $user->addStaffNote($AdminComment);
}
$user->addForumWarning($AdminComment)
    ->modify();

Misc::send_pm($AuthorID, $LoggedUser['ID'], $Subject, $PrivateMessage);

Comments::edit($postId, $Body);

header("Location: $URL");
