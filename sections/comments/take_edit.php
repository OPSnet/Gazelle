<?php

if ($Viewer->disablePosting()) {
    error('Your posting privileges have been removed.', true);
}
authorize();

$body = trim($_POST['body'] ?? '');
if (!strlen($body)) {
    error(404, true);
}

$comment = (new Gazelle\Manager\Comment)->findById((int)($_REQUEST['postid'] ?? 0));
if (is_null($comment)) {
    error(404, true);
}
if ($comment->userId() != $Viewer->id() && !$Viewer->permitted('site_moderate_forums')) {
    error(403, true);
}

$comment->setBody($body)->setEditedUserID($Viewer->id())->modify();
if ((bool)($_POST['pm'] ?? false) && !$comment->isAuthor($Viewer->id())) {
    // Send a PM to the user to notify them of the edit
    $id = $comment->id();
    $url = $comment->publicUrl('action=jump');
    $moderator = "[url=" . $Viewer->url() . "]" . $Viewer->username() . "[/url]";
    (new Gazelle\Manager\User)-> sendPM($comment->userId(), 0,
        "Your comment #$id has been edited",
        "One of your comments has been edited by $moderator: [url]{$url}[/url]"
    );
}

// This gets sent to the browser, which echoes it in place of the old body
echo Text::full_format($body);
