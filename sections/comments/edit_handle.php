<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disablePosting()) {
    Error403::error('Your posting privileges have been removed.');
}
authorize();

$body = trim($_POST['body'] ?? '');
if (!strlen($body)) {
    Error404::error();
}

$comment = new Manager\Comment()->findById((int)($_REQUEST['postid'] ?? 0));
if (is_null($comment)) {
    Error404::error();
}
if ($comment->userId() !== $Viewer->id && !$Viewer->permitted('site_moderate_forums')) {
    Error403::error();
}
$user = new Manager\User()->findById($comment->userId());
if (is_null($user)) {
    Error404::error();
}

$comment->setField('Body', $body)->setField('EditedUserID', $Viewer->id)->modify();
if ((bool)($_POST['pm'] ?? false) && !$comment->isAuthor($Viewer)) {
    // Send a PM to the user to notify them of the edit
    $url = $comment->publicUrl('action=jump');
    $user->inbox()->createSystem(
        "Your comment #{$comment->id} has been edited",
        "One of your comments has been edited by [url={$Viewer->url()}]{$Viewer->username()}[/url]: [url]{$url}[/url]"
    );
}

// This gets sent to the browser, which echoes it in place of the old body
echo \Text::full_format($body);
