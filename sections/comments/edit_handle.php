<?php
/** @phpstan-var \Gazelle\User $Viewer */

if ($Viewer->disablePosting()) {
    error('Your posting privileges have been removed.');
}
authorize();

$body = trim($_POST['body'] ?? '');
if (!strlen($body)) {
    error(404);
}

$comment = (new Gazelle\Manager\Comment())->findById((int)($_REQUEST['postid'] ?? 0));
if (is_null($comment)) {
    error(404);
}
if ($comment->userId() != $Viewer->id() && !$Viewer->permitted('site_moderate_forums')) {
    error(403);
}
$user = (new Gazelle\Manager\User())->findById($comment->userId());
if (is_null($user)) {
    error(404);
}

$comment->setField('Body', $body)->setField('EditedUserID', $Viewer->id())->modify();
if ((bool)($_POST['pm'] ?? false) && !$comment->isAuthor($Viewer)) {
    // Send a PM to the user to notify them of the edit
    $url = $comment->publicUrl('action=jump');
    $user->inbox()->createSystem(
        "Your comment #{$comment->id()} has been edited",
        "One of your comments has been edited by [url={$Viewer->url()}]{$Viewer->username()}[/url]: [url]{$url}[/url]"
    );
}

// This gets sent to the browser, which echoes it in place of the old body
echo Text::full_format($body);
